<?php

namespace App\Services;

use App\Models\Branch;
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

    private const MAX_LONE_ITERATIONS = 300;

    private const MAX_LONE_EVALS = 5000;

    public function distribute(ExamWeek $examWeek, ?string $name = null, ?int $createdBy = null): SeatingPlan
    {
        $yearId = (int) $examWeek->academic_year_id;
        $branchIds = $examWeek->branches()->pluck('branches.id');
        $levels = Branch::whereIn('id', $branchIds)->pluck('grade_level', 'id');

        $students = Student::where('is_active', true)
            ->whereHas('enrollments', fn ($query) => $query
                ->where('academic_year_id', $yearId)
                ->whereIn('branch_id', $branchIds))
            ->with(['enrollments' => fn ($query) => $query
                ->where('academic_year_id', $yearId)
                ->with('branch:id,grade_level')])
            ->get(['id']);

        if ($students->isEmpty()) {
            throw new DistributionException('Dağıtıma dahil aktif öğrenci bulunamadı. Sınav haftasına sınıf ekleyin.');
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
        $studentBranches = $ordered->map(fn (Student $s) => $s->enrollments->first()?->branch_id)->all();
        $studentLevels = $ordered->map(fn (Student $s) => $levels[$s->enrollments->first()?->branch_id] ?? null)->all();

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
                $result = $this->place($studentLevels, $studentBranches, $roomSet);
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
        $need = Student::where('is_active', true)
            ->whereHas('enrollments', fn ($query) => $query
                ->where('academic_year_id', $examWeek->academic_year_id)
                ->whereIn('branch_id', $examWeek->branches()->pluck('branches.id')))
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
        $plan->loadMissing(['examWeek.rooms', 'assignments.seat.room']);

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
        $this->loadPlanStudents($plan);
        $yearId = (int) $plan->examWeek->academic_year_id;

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
                'seats' => $room->seats->map(function ($seat) use ($assignedBySeat, $yearId) {
                    $assignment = $assignedBySeat[$seat->id] ?? null;
                    if (! $assignment) {
                        return [
                            'id' => $seat->id,
                            'row' => $seat->row,
                            'column' => $seat->column,
                            'label' => $seat->label,
                            'assignment_id' => null,
                            'student' => null,
                        ];
                    }

                    $enrollment = $assignment->student->enrollmentForYear($yearId);

                    return [
                        'id' => $seat->id,
                        'row' => $seat->row,
                        'column' => $seat->column,
                        'label' => $seat->label,
                        'assignment_id' => $assignment->id,
                        'student' => [
                            'school_number' => $enrollment?->school_number,
                            'full_name' => $assignment->student->person?->full_name,
                            'branch' => $enrollment?->branch?->name,
                            'grade_level' => $enrollment?->branch?->grade_level,
                            'photo_url' => $assignment->student->person?->photo_path
                                ? asset('storage/'.$assignment->student->person->photo_path)
                                : null,
                        ],
                    ];
                })->all(),
            ])->all();
    }

    /**
     * @return array{count: int, seat_ids: array}
     */
    public function countPlanViolations(SeatingPlan $plan): array
    {
        $this->loadPlanStudents($plan);

        return $this->computeViolations($this->placements($plan));
    }

    /**
     * Planın öğrenciye ait kişi/kayıt/şube verisini tek seferde yükler.
     */
    private function loadPlanStudents(SeatingPlan $plan): void
    {
        $plan->loadMissing([
            'examWeek:id,academic_year_id',
            'assignments.seat',
            'assignments.student.person:id,full_name,photo_path',
            'assignments.student.enrollments.branch:id,name,grade_level',
        ]);
    }

    /**
     * Öğrenciyi boş koltuğa taşı (aynı salon veya salonlar arası).
     *
     * @return array{applied: bool, needs_confirm: bool, message: string, violations: int, violating_seat_ids: array}
     */
    public function moveAssignment(SeatingPlan $plan, int $assignmentId, int $toSeatId, bool $force = false): array
    {
        $this->loadPlanStudents($plan);
        $plan->loadMissing('examWeek.rooms');

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
                'message' => 'Bu taşıma aynı seviyeden öğrencilerin yan yana gelmesine neden olacak.',
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
        $this->loadPlanStudents($plan);

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
        $tmpLevel = $placements[$first->seat_id]['level'];
        $placements[$first->seat_id]['level'] = $placements[$second->seat_id]['level'];
        $placements[$second->seat_id]['level'] = $tmpLevel;

        $after = $this->computeViolations($placements);
        $new = array_values(array_diff($after['seat_ids'], $before));

        if ($new !== [] && ! $force) {
            return [
                'applied' => false,
                'needs_confirm' => true,
                'message' => 'Bu takas aynı seviyeden öğrencilerin yan yana gelmesine neden olacak.',
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
     * @return array<int, array{room_id: int, row: int, col: int, level: int|null, seat_id: int}>
     */
    private function placements(SeatingPlan $plan): array
    {
        $yearId = (int) $plan->examWeek->academic_year_id;
        $out = [];
        foreach ($plan->assignments as $assignment) {
            $out[$assignment->seat_id] = [
                'room_id' => $assignment->seat->room_id,
                'row' => $assignment->seat->row,
                'col' => $assignment->seat->column,
                'level' => $assignment->student->enrollmentForYear($yearId)?->branch?->grade_level,
                'seat_id' => $assignment->seat_id,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array{room_id: int, row: int, col: int, level: int|null, seat_id: int}>  $placements
     * @return array{count: int, seat_ids: array}
     *
     * Komşuluk: aynı salon+satırda art arda gelen AKTİF koltuklar.
     * Sütun numaraları arasındaki sayısal farka bakılmaz; koridor için
     * pasif bırakılan sütunlar (örn. 3 ve 6) komşuluğu kesmez.
     * Aktif ama boş koltuk komşuluğu keser (arada boş sandalye varsa ihlal yok).
     */
    private function computeViolations(array $placements): array
    {
        if ($placements === []) {
            return ['count' => 0, 'seat_ids' => []];
        }

        $roomIds = collect($placements)->pluck('room_id')->unique()->values()->all();
        $activeByRow = Seat::whereIn('room_id', $roomIds)
            ->where('is_active', true)
            ->orderBy('row')
            ->orderBy('column')
            ->get(['id', 'room_id', 'row'])
            ->groupBy(fn ($seat) => $seat->room_id.':'.$seat->row);

        $count = 0;
        $seatIds = [];
        foreach ($activeByRow as $seats) {
            $ids = $seats->pluck('id')->all();
            for ($i = 1; $i < count($ids); $i++) {
                $a = $placements[$ids[$i - 1]] ?? null;
                $b = $placements[$ids[$i]] ?? null;
                if ($a === null || $b === null) {
                    continue;
                }
                if ($a['level'] !== null && $a['level'] === $b['level']) {
                    $count++;
                    $seatIds[] = $a['seat_id'];
                    $seatIds[] = $b['seat_id'];
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
        $groups = $students->groupBy(fn (Student $s) => $s->enrollments->first()?->branch_id)->map(fn (Collection $g) => $g->shuffle()->values())->values();

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
     * @param  array<int, int|null>  $studentLevels  sıra => seviye
     * @param  array<int, int|null>  $studentBranches  sıra => şube
     * @param  Collection<int, Room>  $rooms
     * @return array{rooms: Collection<int, Room>, seats: array, violations: int}
     */
    private function place(array $studentLevels, array $studentBranches, Collection $rooms): array
    {
        $seats = [];
        foreach ($rooms as $room) {
            foreach ($room->seats as $seat) {
                $seats[] = ['seat_id' => $seat->id, 'room_id' => $room->id, 'row' => $seat->row, 'col' => $seat->column, 'student' => null];
            }
        }

        // Komşuluk: aynı salon+satırda art arda gelen AKTİF koltuklar.
        // Sütun numarası farkına bakılmaz; pasif (koridor) sütunlar komşuluğu kesmez.
        $neighbors = array_fill(0, count($seats), []);
        $orderByRow = [];
        foreach ($seats as $i => $seat) {
            $orderByRow[$seat['room_id'].':'.$seat['row']][] = $i;
        }
        foreach ($orderByRow as $list) {
            usort($list, fn ($a, $b) => $seats[$a]['col'] <=> $seats[$b]['col']);
            for ($k = 0; $k < count($list); $k++) {
                if ($k > 0) {
                    $neighbors[$list[$k]][] = $list[$k - 1];
                }
                if ($k < count($list) - 1) {
                    $neighbors[$list[$k]][] = $list[$k + 1];
                }
            }
        }

        $free = array_keys($seats);
        $roomBranchCount = [];
        foreach ($studentLevels as $si => $level) {
            $branch = $studentBranches[$si] ?? null;
            $chosen = null;
            $bestScore = -1;
            foreach ($free as $key => $i) {
                if ($this->conflicts($i, $level, $seats, $neighbors, $studentLevels)) {
                    continue;
                }
                // Çakışmasız koltuklar arasından şube arkadaşlarının çok olduğu salonu yeğle.
                $score = $branch === null ? 0 : ($roomBranchCount[$seats[$i]['room_id']][$branch] ?? 0);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $chosen = $key;
                }
            }
            if ($chosen === null) {
                $chosen = array_key_first($free);
            }
            $seats[$free[$chosen]]['student'] = $si;
            if ($branch !== null) {
                $roomId = $seats[$free[$chosen]]['room_id'];
                $roomBranchCount[$roomId][$branch] = ($roomBranchCount[$roomId][$branch] ?? 0) + 1;
            }
            unset($free[$chosen]);
        }

        $this->repair($seats, $neighbors, $studentLevels);
        $this->reduceLones($seats, $neighbors, $studentLevels, $studentBranches);

        return ['rooms' => $rooms, 'seats' => $seats, 'violations' => $this->countViolations($seats, $neighbors, $studentLevels)];
    }

    /**
     * @param  array  $seats
     * @param  array<int, array<int>>  $neighbors
     * @param  array<int, int|null>  $studentLevels
     */
    private function conflicts(int $i, ?int $level, array $seats, array $neighbors, array $studentLevels): bool
    {
        if ($level === null) {
            return false;
        }
        foreach ($neighbors[$i] as $n) {
            $si = $seats[$n]['student'];
            if ($si !== null && $studentLevels[$si] === $level) {
                return true;
            }
        }

        return false;
    }

    private function countViolations(array $seats, array $neighbors, array $studentLevels): int
    {
        $count = 0;
        foreach ($seats as $i => $seat) {
            if ($seat['student'] === null) {
                continue;
            }
            foreach ($neighbors[$i] as $n) {
                if ($n > $i && $seats[$n]['student'] !== null && $studentLevels[$seats[$n]['student']] !== null && $studentLevels[$seats[$n]['student']] === $studentLevels[$seat['student']]) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function incidentCount(int $i, ?int $level, array $seats, array $neighbors, array $studentLevels): int
    {
        if ($level === null) {
            return 0;
        }
        $count = 0;
        foreach ($neighbors[$i] as $n) {
            $si = $seats[$n]['student'];
            if ($si !== null && $studentLevels[$si] === $level) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array  $seats
     * @param  array<int, array<int>>  $neighbors
     * @param  array<int, int|null>  $studentLevels
     */
    private function repair(array &$seats, array $neighbors, array $studentLevels): void
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
                if ($this->incidentCount($i, $studentLevels[$seats[$i]['student']], $seats, $neighbors, $studentLevels) > 0) {
                    $bad[] = $i;
                }
            }

            if ($bad === []) {
                return;
            }

            $improved = false;

            foreach ($bad as $a) {
                if ($this->trySwap($a, $seats, $neighbors, $studentLevels, $occupied)) {
                    $improved = true;
                    break;
                }
            }

            if (! $improved && $free !== []) {
                foreach ($bad as $a) {
                    if ($this->tryMove($a, $seats, $neighbors, $studentLevels, $free, $occupied)) {
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
     * @param  array<int, int|null>  $studentLevels
     * @param  array<int>  $occupied
     */
    private function trySwap(int $a, array &$seats, array $neighbors, array $studentLevels, array $occupied): bool
    {
        $siA = $seats[$a]['student'];
        $levelA = $studentLevels[$siA];

        foreach ($occupied as $b) {
            if ($b === $a) {
                continue;
            }
            $siB = $seats[$b]['student'];
            $levelB = $studentLevels[$siB];

            if ($levelA === $levelB) {
                continue;
            }

            $shared = $this->adjacent($a, $b, $neighbors) && $levelA === $levelB ? 1 : 0;
            $before = $this->incidentCount($a, $levelA, $seats, $neighbors, $studentLevels)
                + $this->incidentCount($b, $levelB, $seats, $neighbors, $studentLevels)
                - $shared;

            $seats[$a]['student'] = $siB;
            $seats[$b]['student'] = $siA;

            $after = $this->incidentCount($a, $levelB, $seats, $neighbors, $studentLevels)
                + $this->incidentCount($b, $levelA, $seats, $neighbors, $studentLevels)
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
     * @param  array<int, int|null>  $studentLevels
     * @param  array<int>  $free
     * @param  array<int>  $occupied
     */
    private function tryMove(int $a, array &$seats, array $neighbors, array $studentLevels, array &$free, array &$occupied): bool
    {
        $levelA = $studentLevels[$seats[$a]['student']];
        $before = $this->incidentCount($a, $levelA, $seats, $neighbors, $studentLevels);

        if ($before === 0) {
            return false;
        }

        foreach ($free as $key => $f) {
            if ($this->incidentCount($f, $levelA, $seats, $neighbors, $studentLevels) < $before) {
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
     * Yumuşak tercih: aynı şubeden tek kalan öğrencileri, kesin kuralı
     * bozmadan şube arkadaşlarının yanına topla. Garanti vermez.
     *
     * @param  array  $seats
     * @param  array<int, array<int>>  $neighbors
     * @param  array<int, int|null>  $studentLevels
     * @param  array<int, int|null>  $studentBranches
     */
    private function reduceLones(array &$seats, array $neighbors, array $studentLevels, array $studentBranches): void
    {
        $baseViolations = $this->countViolations($seats, $neighbors, $studentLevels);
        $budget = self::MAX_LONE_EVALS;

        for ($iter = 0; $iter < self::MAX_LONE_ITERATIONS; $iter++) {
            $lones = $this->loneSeats($seats, $studentBranches);
            if ($lones === []) {
                return;
            }

            $baseLones = count($lones);
            $improved = false;

            foreach ($lones as $a) {
                if ($budget <= 0) {
                    return;
                }
                if ($this->tryFixLone($a, $seats, $neighbors, $studentLevels, $studentBranches, $baseViolations, $baseLones, $budget)) {
                    $improved = true;
                    break;
                }
            }

            if (! $improved) {
                return;
            }
        }
    }

    /**
     * Salonda şubesinden tek kalan öğrencilerin oturduğu koltuklar.
     * Şubede tek öğrenci varsa (çözümsüz) yalnız sayılmaz.
     *
     * @param  array  $seats
     * @param  array<int, int|null>  $studentBranches
     * @return array<int>
     */
    private function loneSeats(array $seats, array $studentBranches): array
    {
        $roomCounts = [];
        $totals = [];
        foreach ($seats as $seat) {
            $si = $seat['student'];
            if ($si === null) {
                continue;
            }
            $branch = $studentBranches[$si] ?? null;
            if ($branch === null) {
                continue;
            }
            $roomCounts[$seat['room_id']][$branch] = ($roomCounts[$seat['room_id']][$branch] ?? 0) + 1;
            $totals[$branch] = ($totals[$branch] ?? 0) + 1;
        }

        $out = [];
        foreach ($seats as $i => $seat) {
            $si = $seat['student'];
            if ($si === null) {
                continue;
            }
            $branch = $studentBranches[$si] ?? null;
            if ($branch === null) {
                continue;
            }
            if (($roomCounts[$seat['room_id']][$branch] ?? 0) === 1 && ($totals[$branch] ?? 0) > 1) {
                $out[] = $i;
            }
        }

        return $out;
    }

    /**
     * @param  array  $seats
     * @param  array<int, array<int>>  $neighbors
     * @param  array<int, int|null>  $studentLevels
     * @param  array<int, int|null>  $studentBranches
     */
    private function tryFixLone(int $a, array &$seats, array $neighbors, array $studentLevels, array $studentBranches, int $baseViolations, int $baseLones, int &$budget): bool
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

        $branchA = $studentBranches[$seats[$a]['student']] ?? null;
        $roomA = $seats[$a]['room_id'];

        // Önce: yalnızın yanına aynı şubeden arkadaş getir (çifti bozma).
        if ($branchA !== null) {
            // 1) Aynı salondaki boş koltuğa X'li arkadaş taşı.
            foreach ($free as $f) {
                if ($seats[$f]['room_id'] !== $roomA) {
                    continue;
                }
                foreach ($occupied as $b) {
                    if ($seats[$b]['room_id'] === $roomA
                        || ($studentBranches[$seats[$b]['student']] ?? null) !== $branchA
                        || $this->roomBranchCount($seats, $studentBranches, $seats[$b]['room_id'], $branchA) === 2) {
                        continue;
                    }
                    if ($budget-- <= 0) {
                        return false;
                    }
                    $moved = $seats;
                    $moved[$f]['student'] = $moved[$b]['student'];
                    $moved[$b]['student'] = null;

                    if ($this->countViolations($moved, $neighbors, $studentLevels) <= $baseViolations
                        && count($this->loneSeats($moved, $studentBranches)) < $baseLones) {
                        $seats = $moved;

                        return true;
                    }
                }
            }

            // 2) Aynı salondaki başka şubeden biriyle takas et.
            foreach ($occupied as $c) {
                if ($seats[$c]['room_id'] !== $roomA
                    || ($studentBranches[$seats[$c]['student']] ?? null) === $branchA) {
                    continue;
                }
                foreach ($occupied as $b) {
                    if ($seats[$b]['room_id'] === $roomA
                        || ($studentBranches[$seats[$b]['student']] ?? null) !== $branchA
                        || $this->roomBranchCount($seats, $studentBranches, $seats[$b]['room_id'], $branchA) === 2) {
                        continue;
                    }
                    if ($budget-- <= 0) {
                        return false;
                    }
                    $swapped = $seats;
                    $tmp = $swapped[$b]['student'];
                    $swapped[$b]['student'] = $swapped[$c]['student'];
                    $swapped[$c]['student'] = $tmp;

                    if ($this->countViolations($swapped, $neighbors, $studentLevels) <= $baseViolations
                        && count($this->loneSeats($swapped, $studentBranches)) < $baseLones) {
                        $seats = $swapped;

                        return true;
                    }
                }
            }
        }

        // Sonra eski davranış: yalnız öğrenciyi başka koltuğa taşıma dene.
        foreach ($free as $f) {
            if ($budget-- <= 0) {
                return false;
            }
            $moved = $seats;
            $moved[$f]['student'] = $moved[$a]['student'];
            $moved[$a]['student'] = null;

            if ($this->countViolations($moved, $neighbors, $studentLevels) <= $baseViolations
                && count($this->loneSeats($moved, $studentBranches)) < $baseLones) {
                $seats = $moved;

                return true;
            }
        }

        // Sonra başka salonlardaki öğrencilerle takas dene.
        $branchA = $studentBranches[$seats[$a]['student']] ?? null;
        foreach ($occupied as $b) {
            if ($b === $a || $seats[$b]['room_id'] === $seats[$a]['room_id']) {
                continue;
            }
            if (($studentBranches[$seats[$b]['student']] ?? null) === $branchA) {
                continue;
            }
            if ($budget-- <= 0) {
                return false;
            }

            $swapped = $seats;
            $tmp = $swapped[$a]['student'];
            $swapped[$a]['student'] = $swapped[$b]['student'];
            $swapped[$b]['student'] = $tmp;

            if ($this->countViolations($swapped, $neighbors, $studentLevels) <= $baseViolations
                && count($this->loneSeats($swapped, $studentBranches)) < $baseLones) {
                $seats = $swapped;

                return true;
            }
        }

        return false;
    }

    /**
     * Verilen salondaki verilen şubeden öğrenci sayısı.
     *
     * @param  array  $seats
     * @param  array<int, int|null>  $studentBranches
     */
    private function roomBranchCount(array $seats, array $studentBranches, int $roomId, int $branch): int
    {
        $count = 0;
        foreach ($seats as $seat) {
            if ($seat['room_id'] === $roomId
                && $seat['student'] !== null
                && ($studentBranches[$seat['student']] ?? null) === $branch) {
                $count++;
            }
        }

        return $count;
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
