<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ouredu\MultiTenant\Traits\HasUserScope;
use Tests\Support\FakeSession;
use Tests\TestCase;

/**
 * HasUserScope — opt-in role scope from session claims, plus route binding.
 *
 * Data: stage S1 has grades G1, G2; stage S2 has grade G3.
 * Lessons: L1 (G1, math), L2 (G1, science), L3 (G2, math), L4 (G3, math).
 */
class HasUserScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('multi-tenant.session.helper', 'fake_timezone_session');
        config()->set('multi-tenant.query_listener.enabled', false);

        Schema::create('user_scope_grades', function (Blueprint $table) {
            $table->string('uuid')->primary();
            $table->string('educational_stage_uuid')->nullable();
        });

        Schema::create('user_scope_lessons', function (Blueprint $table) {
            $table->string('uuid')->primary();
            $table->string('grade_uuid');
            $table->string('subject_uuid');
        });

        DB::table('user_scope_grades')->insert([
            ['uuid' => 'G1', 'educational_stage_uuid' => 'S1'],
            ['uuid' => 'G2', 'educational_stage_uuid' => 'S1'],
            ['uuid' => 'G3', 'educational_stage_uuid' => 'S2'],
        ]);

        DB::table('user_scope_lessons')->insert([
            ['uuid' => 'L1', 'grade_uuid' => 'G1', 'subject_uuid' => 'math'],
            ['uuid' => 'L2', 'grade_uuid' => 'G1', 'subject_uuid' => 'science'],
            ['uuid' => 'L3', 'grade_uuid' => 'G2', 'subject_uuid' => 'math'],
            ['uuid' => 'L4', 'grade_uuid' => 'G3', 'subject_uuid' => 'math'],
        ]);

        FakeSession::reset();
    }

    protected function tearDown(): void
    {
        FakeSession::reset();

        parent::tearDown();
    }

    /**
     * @return list<string>
     */
    private function scopedLessons(): array
    {
        return UserScopeLesson::query()->userScope()->orderBy('uuid')->pluck('uuid')->all();
    }

    private function claims(array $claims): void
    {
        FakeSession::$session = (object) $claims;
    }

    public function testNoSessionRestrictsNothing(): void
    {
        $this->assertSame(['L1', 'L2', 'L3', 'L4'], $this->scopedLessons());
    }

    public function testNullClaimsRestrictNothing(): void
    {
        $this->claims(['educational_stages' => null, 'grades' => null, 'subjects' => null]);

        $this->assertSame(['L1', 'L2', 'L3', 'L4'], $this->scopedLessons());
    }

    public function testGradesClaimAlone(): void
    {
        $this->claims(['grades' => ['G1']]);

        $this->assertSame(['L1', 'L2'], $this->scopedLessons());
    }

    public function testSubjectsClaimAlone(): void
    {
        $this->claims(['subjects' => ['math']]);

        $this->assertSame(['L1', 'L3', 'L4'], $this->scopedLessons());
    }

    public function testStagesClaimUsesRelationPath(): void
    {
        $this->claims(['educational_stages' => ['S1']]);

        $this->assertSame(['L1', 'L2', 'L3'], $this->scopedLessons());
    }

    public function testClaimsCombineWithAnd(): void
    {
        $this->claims(['educational_stages' => ['S1'], 'grades' => ['G1', 'G3'], 'subjects' => ['math']]);

        $this->assertSame(['L1'], $this->scopedLessons());
    }

    public function testEmptyClaimMatchesNothing(): void
    {
        $this->claims(['grades' => [], 'subjects' => ['math']]);

        $this->assertSame([], $this->scopedLessons());
    }

    public function testCollectionClaimIsAccepted(): void
    {
        $this->claims(['grades' => new Collection(['G2'])]);

        $this->assertSame(['L3'], $this->scopedLessons());
    }

    public function testClaimMissingFromModelMapIsIgnored(): void
    {
        $this->claims(['grades' => ['G1']]);

        $this->assertSame(['G1', 'G2', 'G3'], UserScopeGrade::query()->userScope()->orderBy('uuid')->pluck('uuid')->all());
    }

    public function testDisabledConfigRestrictsNothing(): void
    {
        config()->set('multi-tenant.user_scope.enabled', false);
        $this->claims(['grades' => ['G1']]);

        $this->assertSame(['L1', 'L2', 'L3', 'L4'], $this->scopedLessons());
    }

    public function testRouteBindingResolvesInScopeRecord(): void
    {
        $this->claims(['grades' => ['G1']]);

        $this->assertSame('L1', (new UserScopeLesson())->resolveRouteBinding('L1')?->uuid);
    }

    public function testRouteBindingHidesOutOfScopeRecord(): void
    {
        $this->claims(['grades' => ['G1']]);

        $this->assertNull((new UserScopeLesson())->resolveRouteBinding('L4'));
    }

    public function testRouteBindingWithoutClaimsResolvesAnyRecord(): void
    {
        $this->assertSame('L4', (new UserScopeLesson())->resolveRouteBinding('L4')?->uuid);
    }

    public function testRouteBindingCanBeTurnedOff(): void
    {
        config()->set('multi-tenant.user_scope.route_binding', false);
        $this->claims(['grades' => ['G1']]);

        $this->assertSame('L4', (new UserScopeLesson())->resolveRouteBinding('L4')?->uuid);
    }

    public function testMissingSessionHelperRestrictsNothing(): void
    {
        config()->set('multi-tenant.session.helper', 'no_such_session_helper');
        $this->claims(['grades' => ['G1']]);

        $this->assertSame(['L1', 'L2', 'L3', 'L4'], $this->scopedLessons());
    }

    public function testServiceCanOverrideClaims(): void
    {
        $this->claims(['grades' => ['G1']]);

        $lessons = UserScopeLessonWithOwnClaims::query()->userScope()->orderBy('uuid')->pluck('uuid')->all();

        $this->assertSame(['L3'], $lessons);
    }
}

class UserScopeGrade extends Model
{
    use HasUserScope;

    protected $table = 'user_scope_grades';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected function userScopeMap(): array
    {
        return ['educational_stages' => 'educational_stage_uuid'];
    }
}

class UserScopeLesson extends Model
{
    use HasUserScope;

    protected $table = 'user_scope_lessons';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    public function grade(): BelongsTo
    {
        return $this->belongsTo(UserScopeGrade::class, 'grade_uuid', 'uuid');
    }

    protected function userScopeMap(): array
    {
        return [
            'educational_stages' => 'grade.educational_stage_uuid',
            'grades' => 'grade_uuid',
            'subjects' => 'subject_uuid',
        ];
    }
}

/**
 * A service that keeps its claims somewhere other than the session overrides userScopeClaims().
 */
class UserScopeLessonWithOwnClaims extends UserScopeLesson
{
    protected function userScopeClaims(): array
    {
        return ['grades' => ['G2']];
    }
}
