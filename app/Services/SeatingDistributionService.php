<?php

namespace App\Services;

use App\Models\ExamWeek;
use App\Models\Room;
use App\Models\SeatingAssignment;
use App\Models\SeatingPlan;
use App\Models\Seat;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DistributionException extends \RuntimeException {}

class SeatingDistributionService
{
    private const MAX_SUBSET_ATTEMPTS = 20000;

    private const MAX_REPAIR_ITERATIONS = 500;

    public function distribute(ExamWeek $examWeek, ?string $name = null, ?int $createdBy = null): SeatingPlan
    {
        $students = Student::whereIn('branch_id', $examWeek->branches()->pluck('branches.id'))
            ->where('is_active', true)
            ->get(['id', 'branch_id']);

        if ($students->isEmpty()) {
            throw new DistributionException('Dağıtıma dahil aktif öğrenci bulunamadı. Sınav haftasına şube ekleyin.');
        }

        $rooms = Room::whereIn('id', $examWeek->rooms()->pluck('rooms.id'))
            ->where('is_active', true)
            ->with(['seats' => fn ($query) => $query->where('is_active', true)->orderBy('row')->orderBy('column')])
            ->get()
            ->filter(fn (Room $room) => $room->seats->isNotEmpty())
            ->sort(fn (Room $a, Room $b) => [$b->seats->count(), $a->id] <=> [$a->seats->count(), $b->id])
            ->values();

        if ($rooms->isEmpty()) {
            throw new DistributionException('Dağıtımda kullanılacak aktif salon veya koltuk bulunamadı.');
        }

        $need = $students->count();
        $capacity = $rooms->sum(fn (Room $room) => $room->seats->count());

        if ($capacity < $need) {
            throw new DistributionException("Kapasite yetersiz: {$need} öğrenci var, aktif koltuk sayısı {$capacity}.");
        }

        $ordered = $this->interleaveByBranch($students)->values();
        $studentIds = $ordered->map(fn (Student $s) => $s->id)->all();
        $studentBranches = $ordered->map(fn (Student $s) => $s->branch_id)->all();

        $caps = $rooms->map(fn (Room $room) => $room->seats->count())->all();
        $count = $rooms->count();

        $lower = $count;
        $running = 0;
        foreach ($caps as $i => $cap) {
            $running += $cap;
            if ($running >= $need) {
                $lower = $i + 1;
                break;
            }
        }

        $best = null;
        $attempts = 0;

        for ($k = $lower; $k <= $count; $k++) {
            foreach ($this->feasibleSubsets(range(0, $count - 1), $k, $caps, $need) as $combo) {
                $attempts++;
                if ($attempts > self::MAX_SUBSET_ATTEMPTS) {
                    break 2;
                }
                $roomSet = collect($combo)->map(fn (int $i) => $rooms[$i])->values();
                $result = $this->place($studentBranches, $roomSet);
                if ($best === null || $result['violations'] < $best['violations']) {
                    $best = $result;
                }
                if ($result['violations'] === 0) {
                    break 2;
                }
            }
        }

        return $this->persist($examWeek, $best, $studentIds, $name, $createdBy);
    }

    public function estimateMinRooms(ExamWeek $examWeek): ?int
    {
        $need = Student::whereIn('branch_id', $examWeek->branches()->pluck('branches.id'))
            ->where('is_active', true)
            ->count();

        if ($need === 0) {
            return 0;
        }

        $caps = Room::whereIn('id', $examWeek->rooms()->pluck('rooms.id'))
            ->where('is_active', true)
            ->withCount(['seats as active_seats_count' => fn ($query) => $query->where('is_active', true)])
            ->get()
            ->pluck('active_seats_count')
            ->filter(fn ($c) => $c > 0)
            ->sortDesc()
            ->values();

        $running = 0;
        foreach ($caps as $i => $cap) {
            $running += $cap;
            if ($running >= $need) {
                return $i + 1;
            }
        }

        return null;
    }

    /**
     * @return array{total_students: int, used_rooms: array, unused_rooms: array, used_seats: int, empty_seats: int, violations: int, violating_seat_ids: array}
     */
    public function summary(SeatingPlan $plan): array
    {
        $plan->loadMissing(['examWeek.rooms', 'assignments.seat.room', 'assignments.student']);

        $usedRoomIds = $plan->assignments
            ->map(fn (SeatingAssignment $a) => $a->seat->room_id)
            ->unique()
            ->values()
            ->all();

        $usedRooms = $plan->assignments
            ->map(fn (SeatingAssignment $a) => $a->seat->room)
            ->unique('id')
            ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
            ->values()
            ->map(fn ($room) => ['id' => $room->id, 'name' => $room->name])
            ->all();

        $unusedRooms = $plan->examWeek->rooms
            ->whereNotIn('id', $usedRoomIds)
            ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
            ->values()
            ->map(fn ($room) => ['id' => $room->id, 'name' => $room->name])
            ->all();

        $usedSeats = $plan->assignments->count();
        $emptySeats = $usedRoomIds === []
            ? 0
            : Seat::whereIn('room_id', $usedRoomIds)->where('is_active', true)->count() - $usedSeats;

        $violations = $this->countPlanViolations($plan);

        return [
            'total_students' => $plan->total_students,
            'used_rooms' => $usedRooms,
            'unused_rooms' => $unusedRooms,
            'used_seats' => $usedSeats,
            'empty_seats' => max(0, $emptySeats),
            'violations' => $violations['count'],
            'violating_seat_ids' => $violations['seat_ids'],
        ];
    }

    /**
     * Oturma planının salon bazında ızgara verisi (ekran ve çıktılar için).
     */
    public function seatingGrid(SeatingPlan $plan): array
    {
        $plan->loadMissing(['assignments.seat', 'assignments.student.branch:id,name,grade_level']);

        $assignedBySeat = $plan->assignments->keyBy(fn ($a) => $a->seat_id);
        $roomIds = $assignedBySeat->map(fn ($a) => $a->seat->room_id)->unique()->values()->all();

        return Room::whereIn('id', $roomIds)
            ->with(['seats' => fn ($query) => $query->where('is_active', true)->orderBy('row')->orderBy('column')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room) => [
                'room' => ['id' => $room->id, 'name' => $room->name],
                'maxRow' => $room->seats->max('row') ?? 0,
                'maxColumn' => $room->seats->max('column') ?? 0,
                'seats' => $room->seats->map(fn ($seat) => [
                    'id' => $seat->id,
                    'row' => $seat->row,
                    'column' => $seat->column,
                    'label' => $seat->label,
                    'assignment_id' => $assignedBySeat[$seat->id]->id ?? null,
                    'student' => isset($assignedBySeat[$seat->id]) ? [
                        'school_number' => $assignedBySeat[$seat->id]->student->school_number,
                        'full_name' => $assignedBySeat[$seat->id]->student->full_name,
                        'branch' => $assignedBySeat[$seat->id]->student->branch?->name,
                        'grade_level' => $assignedBySeat[$seat->id]->student->branch?->grade_level,
                        'photo_url' => $assignedBySeat[$seat->id]->student->photo_path
                            ? asset('storage/'.$assignedBySeat[$seat->id]->student->photo_path)
                            : null,
                    ] : null,
                ])->all(),
            ])->all();
    }

    /**
     * @return array{count: int, seat_ids: array}
     */
    public function countPlanViolations(SeatingPlan $plan): array
    {
        $plan->loadMissing(['assignments.seat', 'assignments.student']);

        return $this->computeViolations($this->placements($plan));
    }

    /**
     * Öğrenciyi boş koltuğa taşı (aynı salon veya salonlar arası).
     *
     * @return array{applied: bool, needs_confirm: bool, message: string, violations: int, violating_seat_ids: array}
     */
    public function moveAssignment(SeatingPlan $plan, int $assignmentId, int $toSeatId, bool $force = false): array
    {
        $plan->loadMissing(['assignments.seat', 'assignments.student', 'examWeek.rooms']);

        $assignment = $plan->assignments->firstWhere('id', $assignmentId);
        if (! $assignment) {
            throw new DistributionException('Atama bu plana ait değil.');
        }

        if ($assignment->seat_id === $toSeatId) {
            throw new DistributionException('Kaynak ve hedef koltuk aynı.');
        }

        $seat = Seat::find($toSeatId);
        if (! $seat || ! $seat->is_active) {
            throw new DistributionException('Hedef koltuk aktif değil.');
        }

        $allowedRoomIds = $plan->examWeek->rooms->pluck('id')->all();
        if (! in_array($seat->room_id, $allowedRoomIds, true)) {
            throw new DistributionException('Hedef salon bu sınav haftasında izinli değil.');
        }

        if ($plan->assignments->contains(fn ($a) => $a->seat_id === $toSeatId)) {
            throw new DistributionException('Hedef koltuk dolu. Takas için diğer öğrenciyi seçin.');
        }

        $before = $this->computeViolations($this->placements($plan))['seat_ids'];

        $placements = $this->placements($plan);
        $entry = $placements[$assignment->seat_id];
        unset($placements[$assignment->seat_id]);
        $entry['room_id'] = $seat->room_id;
        $entry['row'] = $seat->row;
        $entry['col'] = $seat->column;
        $placements[$toSeatId] = $entry;

        $after = $this->computeViolations($placements);
        $new = array_values(array_diff($after['seat_ids'], $before));

        if ($new !== [] && ! $force) {
            return [
                'applied' => false,
                'needs_confirm' => true,
                'message' => 'Bu taşıma aynı şubeden öğrencilerin yan yana gelmesine neden olacak.',
                'violations' => $after['count'],
                'violating_seat_ids' => $after['seat_ids'],
            ];
        }

        $assignment->update(['seat_id' => $toSeatId]);
        $this->refreshRoomCount($plan);

        $fresh = $this->countPlanViolations($plan->fresh());

        return [
            'applied' => true,
            'needs_confirm' => false,
            'message' => 'Taşıma kaydedildi.',
            'violations' => $fresh['count'],
            'violating_seat_ids' => $fresh['seat_ids'],
        ];
    }

    /**
     * İki öğrencinin koltuğunu takas et (aynı salon veya salonlar arası).
     *
     * @return array{applied: bool, needs_confirm: bool, message: string, violations: int, violating_seat_ids: array}
     */
    public function swapAssignments(SeatingPlan $plan, int $assignmentId, int $otherAssignmentId, bool $force = false): array
    {
        $plan->loadMissing(['assignments.seat', 'assignments.student']);

        $first = $plan->assignments->firstWhere('id', $assignmentId);
        $second = $plan->assignments->firstWhere('id', $otherAssignmentId);

        if (! $first || ! $second) {
            throw new DistributionException('Atamalardan biri bu plana ait değil.');
        }

        if ($first->id === $second->id || $first->seat_id === $second->seat_id) {
            throw new DistributionException('Takas için iki farklı öğrenci seçin.');
        }

        $before = $this->computeViolations($this->placements($plan))['seat_ids'];

        $placements = $this->placements($plan);
        $tmpBranch = $placements[$first->seat_id]['branch'];
        $placements[$first->seat_id]['branch'] = $placements[$second->seat_id]['branch'];
        $placements[$second->seat_id]['branch'] = $tmpBranch;

        $after = $this->computeViolations($placements);
        $new = array_values(array_diff($after['seat_ids'], $before));

        if ($new !== [] && ! $force) {
            return [
                'applied' => false,
                'needs_confirm' => true,
                'message' => 'Bu takas aynı şubeden öğrencilerin yan yana gelmesine neden olacak.',
                'violations' => $after['count'],
                'violating_seat_ids' => $after['seat_ids'],
            ];
        }

        $firstSeat = (int) $first->seat_id;
        $secondSeat = (int) $second->seat_id;
        $firstId = (int) $first->id;
        $secondId = (int) $second->id;
        $firstStudent = (int) $first->student_id;
        $secondStudent = (int) $second->student_id;

        // Ara adımda unique çakışmaması için geçici koltuk üzerinden takas.
        DB::transaction(function () use ($plan, $firstId, $secondId, $firstSeat, $secondSeat, $firstStudent, $secondStudent) {
            $tempSeatId = Seat::where('is_active', true)
                ->whereNotIn('id', SeatingAssignment::where('seating_plan_id', $plan->id)->pluck('seat_id'))
                ->value('id');

            if ($tempSeatId) {
                SeatingAssignment::whereKey($firstId)->update(['seat_id' => $tempSeatId, 'updated_at' => now()]);
                SeatingAssignment::whereKey($secondId)->update(['seat_id' => $firstSeat, 'updated_at' => now()]);
                SeatingAssignment::whereKey($firstId)->update(['seat_id' => $secondSeat, 'updated_at' => now()]);

                return;
            }

            // Plan tüm aktif koltukları kullanıyor: silip yeniden oluştur.
            SeatingAssignment::whereKey([$firstId, $secondId])->delete();
            $now = now();
            SeatingAssignment::insert([
                ['seating_plan_id' => $plan->id, 'student_id' => $firstStudent, 'seat_id' => $secondSeat, 'created_at' => $now, 'updated_at' => $now],
                ['seating_plan_id' => $plan->id, 'student_id' => $secondStudent, 'seat_id' => $firstSeat, 'created_at' => $now, 'updated_at' => $now],
            ]);
        });

        $fresh = $this->countPlanViolations($plan->fresh());

        return [
            'applied' => true,
            'needs_confirm' => false,
            'message' => 'Takas kaydedildi.',
            'violations' => $fresh['count'],
            'violating_seat_ids' => $fresh['seat_ids'],
        ];
    }

    /**
     * @return array<int, array{room_id: int, row: int, col: int, branch: int, seat_id: int}>
     */
    private function placements(SeatingPlan $plan): array
    {
        $out = [];
        foreach ($plan->assignments as $assignment) {
            $out[$assignment->seat_id] = [
                'room_id' => $assignment->seat->room_id,
                'row' => $assignment->seat->row,
                'col' => $assignment->seat->column,
                'branch' => $assignment->student->branch_id,
                'seat_id' => $assignment->seat_id,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array{room_id: int, row: int, col: int, branch: int, seat_id: int}>  $placements
     * @return array{count: int, seat_ids: array}
     */
    private function computeViolations(array $placements): array
    {
        $byRow = [];
        foreach ($placements as $p) {
            $byRow[$p['room_id'].':'.$p['row']][] = $p;
        }

        $count = 0;
        $seatIds = [];
        foreach ($byRow as $seats) {
            usort($seats, fn ($a, $b) => $a['col'] <=> $b['col']);
            for ($i = 1; $i < count($seats); $i++) {
                if ($seats[$i]['col'] - $seats[$i - 1]['col'] === 1 && $seats[$i]['branch'] === $seats[$i - 1]['branch']) {
                    $count++;
                    $seatIds[] = $seats[$i]['seat_id'];
                    $seatIds[] = $seats[$i - 1]['seat_id'];
                }
            }
        }

        return ['count' => $count, 'seat_ids' => array_values(array_unique($seatIds))];
    }

    private function refreshRoomCount(SeatingPlan $plan): void
    {
        $roomIds = SeatingAssignment::where('seating_plan_id', $plan->id)
            ->with('seat:id,room_id')
            ->get()
            ->map(fn (SeatingAssignment $a) => $a->seat->room_id)
            ->unique();

        $plan->update(['used_room_count' => $roomIds->count()]);
    }

    /**
     * Öğrencileri şubelere göre karıştırarak diz.
     */
    private function interleaveByBranch(Collection $students): Collection
    {
        $groups = $students->groupBy('branch_id')->map(fn (Collection $g) => $g->shuffle()->values())->values();

        $ordered = collect();
        $total = $students->count();
        for ($i = 0; $i < $total; $i++) {
            foreach ($groups as $group) {
                if ($group->isNotEmpty()) {
                    $ordered->push($group->shift());
                }
            }
        }

        return $ordered->values();
    }

    /**
     * @param  array<int>  $indices
     * @param  array<int>  $caps
     * @return array<array<int>>
     */
    private function feasibleSubsets(array $indices, int $k, array $caps, int $need): array
    {
        $combos = [];
        $this->combine($indices, $k, 0, [], $combos);

        $feasible = [];
        foreach ($combos as $combo) {
            $total = 0;
            foreach ($combo as $i) {
                $total += $caps[$i];
            }
            if ($total >= $need) {
                $feasible[] = ['combo' => $combo, 'waste' => $total - $need];
            }
            if (count($feasible) > 50000) {
                break;
            }
        }

        usort($feasible, fn ($a, $b) => $a['waste'] <=> $b['waste']);

        return array_column($feasible, 'combo');
    }

    /**
     * @param  array<int>  $indices
     * @param  array<int>  $current
     * @param  array<array<int>>  $out
     */
    private function combine(array $indices, int $k, int $start, array $current, array &$out): void
    {
        if (count($current) === $k) {
            $out[] = $current;

            return;
        }

        for ($i = $start; $i < count($indices); $i++) {
            $current[] = $indices[$i];
            $this->combine($indices, $k, $i + 1, $current, $out);
            array_pop($current);
        }
    }

    /**
     * @param  array<int>  $studentBranches  sıra => şube id
     * @param  Collection<int, Room>  $rooms
     * @return array{rooms: Collection<int, Room>, seats: array, violations: int}
     */
    private function place(array $studentBranches, Collection $rooms): array
    {
        $seats = [];
        foreach ($rooms as $room) {
            foreach ($room->seats as $seat) {
                $seats[] = ['seat_id' => $seat->id, 'room_id' => $room->id, 'row' => $seat->row, 'col' => $seat->column, 'student' => null];
            }
        }

        $pos = [];
        foreach ($seats as $i => $seat) {
            $pos[$seat['room_id'].':'.$seat['row'].':'.$seat['col']] = $i;
        }

        $neighbors = [];
        foreach ($seats as $i => $seat) {
            $list = [];
            foreach ([-1, 1] as $d) {
                $key = $seat['room_id'].':'.$seat['row'].':'.($seat['col'] + $d);
                if (isset($pos[$key])) {
                    $list[] = $pos[$key];
                }
            }
            $neighbors[$i] = $list;
        }

        $free = array_keys($seats);
        foreach ($studentBranches as $si => $branchId) {
            $chosen = null;
            foreach ($free as $key => $i) {
                if (! $this->conflicts($i, $branchId, $seats, $neighbors, $studentBranches)) {
                    $chosen = $key;
                    break;
                }
            }
            if ($chosen === null) {
                $chosen = array_key_first($free);
            }
            $seats[$free[$chosen]]['student'] = $si;
            unset($free[$chosen]);
        }

        $this->repair($seats, $neighbors, $studentBranches);

        return ['rooms' => $rooms, 'seats' => $seats, 'violations' => $this->countViolations($seats, $neighbors, $studentBranches)];
    }

    /**
     * @param  array  $seats
     * @param  array<int, array<int>>  $neighbors
     * @param  array<int, int>  $studentBranches
     */
    private function conflicts(int $i, int $branchId, array $seats, array $neighbors, array $studentBranches): bool
    {
        foreach ($neighbors[$i] as $n) {
            $si = $seats[$n]['student'];
            if ($si !== null && $studentBranches[$si] === $branchId) {
                return true;
            }
        }

        return false;
    }

    private function countViolations(array $seats, array $neighbors, array $studentBranches): int
    {
        $count = 0;
        foreach ($seats as $i => $seat) {
            if ($seat['student'] === null) {
                continue;
            }
            foreach ($neighbors[$i] as $n) {
                if ($n > $i && $seats[$n]['student'] !== null && $studentBranches[$seats[$n]['student']] === $studentBranches[$seat['student']]) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function incidentCount(int $i, int $branchId, array $seats, array $neighbors, array $studentBranches): int
    {
        $count = 0;
        foreach ($neighbors[$i] as $n) {
            $si = $seats[$n]['student'];
            if ($si !== null && $studentBranches[$si] === $branchId) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array  $seats
     * @param  array<int, array<int>>  $neighbors
     * @param  array<int, int>  $studentBranches
     */
    private function repair(array &$seats, array $neighbors, array $studentBranches): void
    {
        $occupied = [];
        $free = [];
        foreach ($seats as $i => $seat) {
            if ($seat['student'] === null) {
                $free[] = $i;
            } else {
                $occupied[] = $i;
            }
        }

        for ($iter = 0; $iter < self::MAX_REPAIR_ITERATIONS; $iter++) {
            $bad = [];
            foreach ($occupied as $i) {
                if ($this->incidentCount($i, $studentBranches[$seats[$i]['student']], $seats, $neighbors, $studentBranches) > 0) {
                    $bad[] = $i;
                }
            }

            if ($bad === []) {
                return;
            }

            $improved = false;

            foreach ($bad as $a) {
                if ($this->trySwap($a, $seats, $neighbors, $studentBranches, $occupied)) {
                    $improved = true;
                    break;
                }
            }

            if (! $improved && $free !== []) {
                foreach ($bad as $a) {
                    if ($this->tryMove($a, $seats, $neighbors, $studentBranches, $free, $occupied)) {
                        $improved = true;
                        break;
                    }
                }
            }

            if (! $improved) {
                return;
            }
        }
    }

    private function adjacent(int $a, int $b, array $neighbors): bool
    {
        return in_array($b, $neighbors[$a], true);
    }

    /**
     * @param  array  $seats
     * @param  array<int, array<int>>  $neighbors
     * @param  array<int, int>  $studentBranches
     * @param  array<int>  $occupied
     */
    private function trySwap(int $a, array &$seats, array $neighbors, array $studentBranches, array $occupied): bool
    {
        $siA = $seats[$a]['student'];
        $branchA = $studentBranches[$siA];

        foreach ($occupied as $b) {
            if ($b === $a) {
                continue;
            }
            $siB = $seats[$b]['student'];
            $branchB = $studentBranches[$siB];

            if ($branchA === $branchB) {
                continue;
            }

            $shared = $this->adjacent($a, $b, $neighbors) && $branchA === $branchB ? 1 : 0;
            $before = $this->incidentCount($a, $branchA, $seats, $neighbors, $studentBranches)
                + $this->incidentCount($b, $branchB, $seats, $neighbors, $studentBranches)
                - $shared;

            $seats[$a]['student'] = $siB;
            $seats[$b]['student'] = $siA;

            $after = $this->incidentCount($a, $branchB, $seats, $neighbors, $studentBranches)
                + $this->incidentCount($b, $branchA, $seats, $neighbors, $studentBranches)
                - $shared;

            if ($after < $before) {
                return true;
            }

            $seats[$a]['student'] = $siA;
            $seats[$b]['student'] = $siB;
        }

        return false;
    }

    /**
     * @param  array  $seats
     * @param  array<int, array<int>>  $neighbors
     * @param  array<int, int>  $studentBranches
     * @param  array<int>  $free
     * @param  array<int>  $occupied
     */
    private function tryMove(int $a, array &$seats, array $neighbors, array $studentBranches, array &$free, array &$occupied): bool
    {
        $branchA = $studentBranches[$seats[$a]['student']];
        $before = $this->incidentCount($a, $branchA, $seats, $neighbors, $studentBranches);

        if ($before === 0) {
            return false;
        }

        foreach ($free as $key => $f) {
            if ($this->incidentCount($f, $branchA, $seats, $neighbors, $studentBranches) < $before) {
                $seats[$f]['student'] = $seats[$a]['student'];
                $seats[$a]['student'] = null;
                unset($free[$key]);
                $free[] = $a;
                $occupied[array_search($a, $occupied, true)] = $f;

                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{rooms: Collection<int, Room>, seats: array, violations: int}  $result
     * @param  array<int>  $studentIds  sıra => öğrenci id (place() ile aynı sıra)
     */
    private function persist(ExamWeek $examWeek, array $result, array $studentIds, ?string $name, ?int $createdBy): SeatingPlan
    {
        $plan = SeatingPlan::create([
            'exam_week_id' => $examWeek->id,
            'name' => $name ? trim($name) : null,
            'status' => 'draft',
            'total_students' => count($studentIds),
            'used_room_count' => $result['rooms']->count(),
            'created_by' => $createdBy,
        ]);

        $now = now();
        $rows = [];
        foreach ($result['seats'] as $seat) {
            if ($seat['student'] === null) {
                continue;
            }
            $rows[] = [
                'seating_plan_id' => $plan->id,
                'student_id' => $studentIds[$seat['student']],
                'seat_id' => $seat['seat_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            SeatingAssignment::insert($chunk);
        }

        return $plan;
    }
}
