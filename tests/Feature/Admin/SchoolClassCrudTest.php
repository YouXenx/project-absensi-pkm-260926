<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\AccessDenied;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolClassCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_index_renders_the_server_side_table_without_embedding_rows(): void
    {
        SchoolClass::factory()->create(['class_name' => 'Kelas 9Z']);

        $this->actingAs($this->admin)
            ->get(route('admin.kelas.index'))
            ->assertOk()
            ->assertSee('data-datatable', false)
            ->assertSee('data-url="'.route('admin.kelas.data').'"', false)
            ->assertDontSee('Kelas 9Z');
    }

    public function test_data_endpoint_follows_the_datatables_protocol(): void
    {
        foreach (['Kelas 1A', 'Kelas 1B', 'Kelas 2A'] as $name) {
            SchoolClass::factory()->create(['class_name' => $name]);
        }
        Student::factory()->count(3)->for(SchoolClass::firstWhere('class_name', 'Kelas 1B'))->create();

        $this->actingAs($this->admin)
            ->getJson(route('admin.kelas.data', [
                'draw' => 4,
                'start' => 0,
                'length' => 2,
                'search' => ['value' => 'Kelas 1'],
                'columns' => [['data' => 'class_name'], ['data' => 'students_count'], ['data' => 'actions']],
                'order' => [['column' => 0, 'dir' => 'desc']],
            ]))
            ->assertOk()
            ->assertJsonPath('draw', 4)
            ->assertJsonPath('recordsTotal', 3)
            ->assertJsonPath('recordsFiltered', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.class_name', 'Kelas 1B')
            ->assertJsonPath('data.0.students_count', 3)
            ->assertJsonPath('data.1.class_name', 'Kelas 1A');
    }

    public function test_data_endpoint_only_returns_the_requested_page(): void
    {
        SchoolClass::factory()->count(15)->create();

        $this->actingAs($this->admin)
            ->getJson(route('admin.kelas.data', ['draw' => 1, 'start' => 10, 'length' => 10]))
            ->assertJsonPath('recordsTotal', 15)
            ->assertJsonCount(5, 'data');
    }

    public function test_data_endpoint_escapes_html(): void
    {
        SchoolClass::factory()->create(['class_name' => '<script>x</script>']);

        $this->actingAs($this->admin)
            ->getJson(route('admin.kelas.data', ['draw' => 1]))
            ->assertJsonPath('data.0.class_name', '&lt;script&gt;x&lt;/script&gt;');
    }

    public function test_data_endpoint_rejects_oversized_pages(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.kelas.data', ['length' => 100000]))
            ->assertUnprocessable();
    }

    public function test_admin_can_create_a_class(): void
    {
        $this->actingAs($this->admin)->get(route('admin.kelas.index'))->assertOk()->assertSee('id="class-modal"', false);

        $this->actingAs($this->admin)
            ->post(route('admin.kelas.store'), ['class_name' => 'Kelas 7C'])
            ->assertRedirect(route('admin.kelas.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('classes', ['class_name' => 'Kelas 7C']);
    }

    public function test_class_name_must_be_unique(): void
    {
        SchoolClass::factory()->create(['class_name' => 'Kelas 1A']);

        $this->actingAs($this->admin)
            ->postJson(route('admin.kelas.store'), ['class_name' => 'Kelas 1A'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['class_name' => 'Nama kelas sudah digunakan.']);
    }

    public function test_admin_can_update_a_class_and_keep_its_own_name(): void
    {
        $schoolClass = SchoolClass::factory()->create(['class_name' => 'Kelas 1A']);

        $this->actingAs($this->admin)
            ->getJson(route('admin.kelas.edit', $schoolClass))
            ->assertOk()
            ->assertJsonPath('action', route('admin.kelas.update', $schoolClass))
            ->assertJsonPath('method', 'PUT')
            ->assertJsonPath('values.class_name', 'Kelas 1A');

        $this->actingAs($this->admin)
            ->put(route('admin.kelas.update', $schoolClass), ['class_name' => 'Kelas 1A'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->put(route('admin.kelas.update', $schoolClass), ['class_name' => 'Kelas 1 Unggulan'])
            ->assertRedirect(route('admin.kelas.index'));

        $this->assertSame('Kelas 1 Unggulan', $schoolClass->fresh()->class_name);
    }

    public function test_admin_can_delete_an_empty_class(): void
    {
        $schoolClass = SchoolClass::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.kelas.destroy', $schoolClass))
            ->assertRedirect(route('admin.kelas.index'))
            ->assertSessionHas('success');

        $this->assertModelMissing($schoolClass);
    }

    public function test_class_with_students_cannot_be_deleted(): void
    {
        $schoolClass = SchoolClass::factory()->has(Student::factory()->count(2))->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.kelas.destroy', $schoolClass))
            ->assertRedirect(route('admin.kelas.index'))
            ->assertSessionHas('error');

        $this->assertModelExists($schoolClass);
    }

    public function test_guru_cannot_manage_classes(): void
    {
        $guru = User::factory()->guru()->create();
        $schoolClass = SchoolClass::factory()->create();

        $this->actingAs($guru)->getJson(route('admin.kelas.data'))->assertForbidden()->assertJson(['message' => AccessDenied::MESSAGE]);
        $this->actingAs($guru)->post(route('admin.kelas.store'), ['class_name' => 'X'])->assertRedirect(route('guru.dashboard'));
        $this->actingAs($guru)->delete(route('admin.kelas.destroy', $schoolClass))->assertRedirect(route('guru.dashboard'));

        $this->assertModelExists($schoolClass);
        $this->assertDatabaseMissing('classes', ['class_name' => 'X']);
    }
}
