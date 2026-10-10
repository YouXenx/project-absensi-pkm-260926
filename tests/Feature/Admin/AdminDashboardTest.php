<?php

namespace Tests\Feature\Admin;

use App\Gender;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_dashboard_shows_statistics_from_the_erd_tables(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->guru()->count(3)->create();
        User::factory()->guru()->inactive()->create();

        $classA = SchoolClass::factory()->create(['class_name' => 'Kelas 1A']);
        SchoolClass::factory()->create(['class_name' => 'Kelas 1B']);
        Student::factory()->count(3)->for($classA)->create(['gender' => Gender::Male]);
        Student::factory()->count(2)->for($classA)->create(['gender' => Gender::Female]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertViewHas('stats', fn (array $stats): bool => $stats === [
                'teachers' => 4,
                'activeTeachers' => 3,
                'classes' => 2,
                'emptyClasses' => 1,
                'students' => 5,
                'maleStudents' => 3,
                'femaleStudents' => 2,
                'averagePerClass' => 2.5,
            ])
            ->assertSee('Total guru')
            ->assertSee('Total kelas')
            ->assertSee('Total siswa')
            ->assertSeeInOrder(['Kelas 1A', '5', 'Kelas 1B', '0']);
    }

    public function test_dashboard_works_on_an_empty_database(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Belum ada kelas.');
    }

    public function test_dashboard_has_no_shortcuts_to_add_teachers_or_students(): void
    {
        // Adding records belongs to the master data pages; the dashboard only summarises.
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.guru.index').'#tambah', false)
            ->assertDontSee(route('admin.siswa.index').'#tambah', false);
    }

    public function test_admin_sidebar_links_to_the_crud_pages(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'));

        foreach (['admin.guru.index', 'admin.kelas.index', 'admin.siswa.index', 'admin.akun.edit'] as $route) {
            $response->assertSee('href="'.route($route).'"', false);
        }
    }
}
