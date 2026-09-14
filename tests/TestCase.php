<?php

namespace Tests;

use App\Models\School;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Test kullanıcısını ilk okula bağlar ve oturum okulunu seçer.
     * Böylece mevcut testler okul bağlamı middleware'inden etkilenmez.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        if ($user instanceof \App\Models\User) {
            $user->loadMissing('schools');

            if ($user->schools->isEmpty() && ($school = School::first())) {
                $user->schools()->attach($school->id);
                $user->load('schools');
            }

            $schoolId = $user->schools->first()?->id;

            if ($schoolId) {
                $this->withSession(['current_school_id' => $schoolId]);
            }
        }

        return $this;
    }
}
