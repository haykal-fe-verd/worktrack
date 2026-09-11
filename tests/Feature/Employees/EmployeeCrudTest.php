<?php

namespace Tests\Feature\Employees;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function viewer(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('viewer');

        return $user;
    }

    /**
     * Build the headers needed to make a GET request return Inertia's JSON
     * payload instead of the full Blade view. The Blade view resolves the
     * page component through Vite (`@vite("resources/js/Pages/{$page['component']}.tsx")`),
     * which doesn't exist yet — the frontend page is added in a later task.
     * Sending `X-Inertia: true` makes `Inertia::render()` return the raw
     * JSON payload instead (see `Inertia\Response::toResponse()`), which
     * also requires a matching `X-Inertia-Version` header or the middleware
     * responds with a 409 conflict instructing the client to reload.
     *
     * Because the response is a `JsonResponse` (not a Blade `View`), the
     * `assertInertia()` test helper — which asserts against `viewData('page')` —
     * cannot be used here; assertions below read the JSON body directly.
     *
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        $version = file_exists($manifest = public_path('build/manifest.json'))
            ? hash_file('xxh128', $manifest)
            : '';

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
        ];
    }

    public function test_viewer_sees_masked_nik_and_no_rekening_in_the_list(): void
    {
        $viewer = $this->viewer();
        Employee::factory()->create(['nik' => '3513126804000001', 'no_rekening' => '1923973699']);

        $response = $this->actingAs($viewer)->get('/employees', $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('component', 'Employees/Index');
        $response->assertJsonPath('props.employees.data.0.nik', '3513******0001');
        $response->assertJsonPath('props.employees.data.0.no_rekening', '1923******3699');
        $response->assertJsonPath('props.canManage', false);
    }

    public function test_admin_sees_full_nik_and_no_rekening_in_the_list(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nik' => '3513126804000001', 'no_rekening' => '1923973699']);

        $response = $this->actingAs($admin)->get('/employees', $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('props.employees.data.0.nik', '3513126804000001');
        $response->assertJsonPath('props.employees.data.0.no_rekening', '1923973699');
        $response->assertJsonPath('props.canManage', true);
    }

    public function test_viewer_cannot_create_an_employee(): void
    {
        $viewer = $this->viewer();

        $this->actingAs($viewer)->get('/employees/create')->assertForbidden();
        $this->actingAs($viewer)->post('/employees', [])->assertForbidden();
    }

    public function test_admin_can_create_an_employee_with_valid_data(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/employees', [
            'nama' => 'Budi Santoso',
            'nik' => '3513126804000099',
            'alamat' => 'Jl. Merdeka No. 1',
            'no_rekening' => '1923973699',
            'nama_bank' => 'BCA',
        ]);

        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('employees', [
            'nik' => '3513126804000099',
            'nama' => 'Budi Santoso',
            'status' => 'aktif',
        ]);
    }

    public function test_nik_must_be_exactly_sixteen_digits(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/employees', [
            'nama' => 'Budi Santoso',
            'nik' => '12345',
            'alamat' => 'Jl. Merdeka No. 1',
            'no_rekening' => '1923973699',
        ]);

        $response->assertSessionHasErrors('nik');
    }

    public function test_nik_must_be_unique(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nik' => '3513126804000099']);

        $response = $this->actingAs($admin)->post('/employees', [
            'nama' => 'Nama Lain',
            'nik' => '3513126804000099',
            'alamat' => 'Jl. Merdeka No. 2',
            'no_rekening' => '1111111111',
        ]);

        $response->assertSessionHasErrors('nik');
    }

    public function test_admin_can_update_an_employee_and_keep_its_own_nik(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create(['nik' => '3513126804000099']);

        $response = $this->actingAs($admin)->put("/employees/{$employee->id}", [
            'nama' => 'Nama Diperbarui',
            'nik' => '3513126804000099',
            'alamat' => 'Alamat Baru',
            'no_rekening' => '2222222222',
        ]);

        $response->assertRedirect(route('employees.index'));
        $employee->refresh();
        $this->assertSame('Nama Diperbarui', $employee->nama);
        $this->assertSame('2222222222', $employee->no_rekening);
    }

    public function test_admin_can_toggle_employee_status(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create();

        $this->actingAs($admin)
            ->patch("/employees/{$employee->id}/toggle-status")
            ->assertRedirect(route('employees.index'));

        $this->assertSame('non_aktif', $employee->fresh()->status->value);

        $this->actingAs($admin)->patch("/employees/{$employee->id}/toggle-status");

        $this->assertSame('aktif', $employee->fresh()->status->value);
    }

    public function test_search_filters_by_name_or_nik(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nama' => 'Budi Santoso', 'nik' => '1111111111111111']);
        Employee::factory()->create(['nama' => 'Siti Aminah', 'nik' => '2222222222222222']);

        $response = $this->actingAs($admin)->get('/employees?search=Budi', $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonCount(1, 'props.employees.data');
        $response->assertJsonPath('props.employees.data.0.nama', 'Budi Santoso');
    }

    public function test_status_filter_narrows_the_list(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nama' => 'Aktif Satu']);
        Employee::factory()->nonAktif()->create(['nama' => 'Non Aktif Satu']);

        $response = $this->actingAs($admin)->get('/employees?status=non_aktif', $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonCount(1, 'props.employees.data');
        $response->assertJsonPath('props.employees.data.0.nama', 'Non Aktif Satu');
    }
}
