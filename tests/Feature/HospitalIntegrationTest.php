<?php

namespace Tests\Feature;

use App\Contracts\HospitalPatientProvider;
use App\Data\HospitalizedPatient;
use App\Enums\UserRole;
use App\Exceptions\HospitalIntegrationException;
use App\Models\User;
use App\Services\HospitalPatientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HospitalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hospital.url' => 'https://hospital.example.test',
            'hospital.token' => 'test-secret-token',
            'hospital.timeout' => 7,
        ]);
    }

    public function test_dto_preserves_normalized_patient_data(): void
    {
        $patient = new HospitalizedPatient('00123', '00456', 'Juan Pérez López', '0007', '204');

        $this->assertSame('00123', $patient->externalPatientId);
        $this->assertSame('00456', $patient->externalHospitalizationId);
        $this->assertSame('Juan Pérez López', $patient->patientName);
        $this->assertSame('0007', $patient->externalRoomId);
        $this->assertSame('204', $patient->roomNumber);
    }

    public function test_provider_sends_expected_authenticated_json_request_and_maps_response(): void
    {
        Http::fake([
            'https://hospital.example.test/api/integrations/warehouse/active-patients' => Http::response([
                'data' => [[
                    'patient_id' => 'abc-123',
                    'patient_name' => 'Juan Pérez',
                    'hospitalization_id' => 'stay-456',
                    'room_number' => '204',
                    'room_id' => 'room-7',
                ]],
            ]),
        ]);

        $patients = app(HospitalPatientProvider::class)->activePatients();

        $this->assertCount(1, $patients);
        $this->assertInstanceOf(HospitalizedPatient::class, $patients->first());
        $this->assertSame('abc-123', $patients->first()->externalPatientId);
        $this->assertSame('stay-456', $patients->first()->externalHospitalizationId);
        $this->assertSame('room-7', $patients->first()->externalRoomId);
        $this->assertSame(7, config('hospital.timeout'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://hospital.example.test/api/integrations/warehouse/active-patients'
            && $request->hasHeader('Authorization', 'Bearer test-secret-token')
            && $request->hasHeader('Accept', 'application/json'));
    }

    public function test_provider_supports_multiple_empty_and_numeric_id_responses(): void
    {
        Http::fakeSequence()
            ->push(['data' => [
                ['patient_id' => 12345, 'hospitalization_id' => 5001, 'patient_name' => 'Paciente B', 'room_id' => 10, 'room_number' => 10],
                ['patient_id' => 'uuid-2', 'hospitalization_id' => 'stay-2', 'patient_name' => 'Paciente A', 'room_id' => 'room-2', 'room_number' => '2'],
            ]])
            ->push(['data' => []]);

        $patients = app(HospitalPatientProvider::class)->activePatients();
        $this->assertCount(2, $patients);
        $this->assertSame('12345', $patients->first()->externalPatientId);
        $this->assertTrue(app(HospitalPatientProvider::class)->activePatients()->isEmpty());
        $this->assertSame('5001', $patients->first()->externalHospitalizationId);
        $this->assertSame('10', $patients->first()->externalRoomId);
        $this->assertSame('10', $patients->first()->roomNumber);
    }

    public function test_application_service_orders_by_room_naturally_then_name(): void
    {
        $this->bindPatients([
            new HospitalizedPatient('3', '103', 'Zoe', '10', '10'),
            new HospitalizedPatient('2', '102', 'Beatriz', '2', '2'),
            new HospitalizedPatient('1', '101', 'Ana', '2', '2'),
        ]);

        $this->assertSame(
            ['Ana', 'Beatriz', 'Zoe'],
            app(HospitalPatientService::class)->activePatients()->pluck('patientName')->all(),
        );
    }

    public function test_provider_keeps_mother_and_newborn_hospitalizations_in_the_same_room(): void
    {
        Http::fake(fn () => Http::response(['data' => [
            [
                'patient_id' => '10',
                'hospitalization_id' => '100',
                'patient_name' => 'Paciente A',
                'room_id' => '5',
                'room_number' => '204',
            ],
            [
                'patient_id' => '11',
                'hospitalization_id' => '101',
                'patient_name' => 'Paciente B',
                'room_id' => '5',
                'room_number' => '204',
            ],
        ]]));

        $patients = app(HospitalPatientProvider::class)->activePatients();

        $this->assertCount(2, $patients);
        $this->assertSame(['100', '101'], $patients->pluck('externalHospitalizationId')->all());
        $this->assertSame(['5', '5'], $patients->pluck('externalRoomId')->all());
        $this->assertSame(['204', '204'], $patients->pluck('roomNumber')->all());
    }

    public function test_provider_does_not_deduplicate_reentries_by_patient_id_and_ignores_extra_fields(): void
    {
        Http::fake(fn () => Http::response(['data' => [
            [
                'patient_id' => '15',
                'hospitalization_id' => '100',
                'patient_name' => 'Paciente Reingreso',
                'room_id' => '5',
                'room_number' => '204',
                'diagnosis' => 'Campo adicional ignorado',
            ],
            [
                'patient_id' => '15',
                'hospitalization_id' => '250',
                'patient_name' => 'Paciente Reingreso',
                'room_id' => '8',
                'room_number' => '301',
            ],
        ]]));

        $patients = app(HospitalPatientProvider::class)->activePatients();

        $this->assertCount(2, $patients);
        $this->assertSame(['15', '15'], $patients->pluck('externalPatientId')->all());
        $this->assertSame(['100', '250'], $patients->pluck('externalHospitalizationId')->all());
    }

    public function test_missing_data_and_incomplete_patient_fields_are_invalid(): void
    {
        $invalidPayloads = [
            [],
            ['data' => [['hospitalization_id' => '10', 'patient_name' => 'Paciente', 'room_id' => '5', 'room_number' => '204']]],
            ['data' => [['patient_id' => '1', 'patient_name' => 'Paciente', 'room_id' => '5', 'room_number' => '204']]],
            ['data' => [['patient_id' => '1', 'hospitalization_id' => '10', 'room_id' => '5', 'room_number' => '204']]],
            ['data' => [['patient_id' => '1', 'hospitalization_id' => '10', 'patient_name' => 'Paciente', 'room_number' => '204']]],
            ['data' => [['patient_id' => '1', 'hospitalization_id' => '10', 'patient_name' => 'Paciente', 'room_id' => '5']]],
            ['data' => [['patient_id' => '', 'hospitalization_id' => '10', 'patient_name' => 'Paciente', 'room_id' => '5', 'room_number' => '204']]],
            ['data' => [['patient_id' => '1', 'hospitalization_id' => ' ', 'patient_name' => 'Paciente', 'room_id' => '5', 'room_number' => '204']]],
            ['data' => [['patient_id' => '1', 'hospitalization_id' => '10', 'patient_name' => ' ', 'room_id' => '5', 'room_number' => '204']]],
            ['data' => [['patient_id' => '1', 'hospitalization_id' => '10', 'patient_name' => 'Paciente', 'room_id' => '', 'room_number' => '204']]],
            ['data' => [['patient_id' => '1', 'hospitalization_id' => '10', 'patient_name' => 'Paciente', 'room_id' => '5', 'room_number' => '']]],
        ];

        foreach ($invalidPayloads as $payload) {
            Http::fake(fn () => Http::response($payload));
            $this->expectIntegrationException(fn () => app(HospitalPatientProvider::class)->activePatients(), 'respuesta que no pudo procesarse');
        }
    }

    public function test_invalid_json_is_a_controlled_contract_error(): void
    {
        Http::fake(fn () => Http::response('{invalid-json', 200, ['Content-Type' => 'application/json']));

        $this->expectIntegrationException(
            fn () => app(HospitalPatientProvider::class)->activePatients(),
            'respuesta que no pudo procesarse',
        );
    }

    public function test_authentication_http_errors_are_controlled(): void
    {
        foreach ([401, 403] as $status) {
            Http::fake(fn () => Http::response(['message' => 'technical'], $status));
            $this->expectIntegrationException(
                fn () => app(HospitalPatientProvider::class)->activePatients(),
                'No fue posible autenticar la conexión',
            );
        }
    }

    public function test_server_and_connection_errors_are_controlled(): void
    {
        Http::fake(fn () => Http::response([], 500));
        $this->expectIntegrationException(
            fn () => app(HospitalPatientProvider::class)->activePatients(),
            'no está disponible',
        );

        Http::fake(fn () => throw new ConnectionException('timeout or dns failure'));
        $this->expectIntegrationException(
            fn () => app(HospitalPatientProvider::class)->activePatients(),
            'no está disponible',
        );
    }

    public function test_administrator_and_root_can_open_diagnostic_screen_with_patient_data(): void
    {
        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->bindPatients([new HospitalizedPatient('external-99', 'stay-999', 'Paciente Diagnóstico', 'room-7', '204')]);
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('hospital-integration.patients'))
                ->assertOk()
                ->assertSee('Integración con Hospitalización')
                ->assertSee('Paciente Diagnóstico')
                ->assertSee('ID paciente')
                ->assertSee('ID hospitalización')
                ->assertSee('204')
                ->assertSee('external-99')
                ->assertSee('stay-999')
                ->assertDontSee('room-7')
                ->assertSee(route('hospital-integration.patients'), false)
                ->assertDontSee('test-secret-token');
        }
    }

    public function test_diagnostic_screen_handles_empty_and_external_error_safely(): void
    {
        $user = User::factory()->administrator()->create();
        $this->bindPatients([]);
        $this->actingAs($user)->get(route('hospital-integration.patients'))
            ->assertOk()->assertSee('No hay pacientes hospitalizados actualmente.');

        $this->app->instance(HospitalPatientProvider::class, new class implements HospitalPatientProvider
        {
            public function activePatients(): Collection
            {
                throw HospitalIntegrationException::authenticationFailed();
            }
        });
        $this->get(route('hospital-integration.patients'))
            ->assertOk()
            ->assertSee('No fue posible consultar los pacientes hospitalizados.')
            ->assertSee('No fue posible autenticar la conexión')
            ->assertDontSee('test-secret-token')
            ->assertDontSee('hospital.example.test');
    }

    public function test_only_administrators_can_access_diagnostic_screen_or_navigation(): void
    {
        foreach ([UserRole::WAREHOUSE_MANAGER, UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('hospital-integration.patients'))->assertForbidden();
            $this->get(route('home'))->assertOk()->assertDontSee(route('hospital-integration.patients'), false);
        }

        auth()->logout();
        $this->get(route('hospital-integration.patients'))->assertRedirect(route('login'));
    }

    /** @param list<HospitalizedPatient> $patients */
    private function bindPatients(array $patients): void
    {
        $this->app->instance(HospitalPatientProvider::class, new class($patients) implements HospitalPatientProvider
        {
            public function __construct(private readonly array $patients) {}

            public function activePatients(): Collection
            {
                return collect($this->patients);
            }
        });
    }

    private function expectIntegrationException(callable $callback, string $safeMessageFragment): void
    {
        try {
            $callback();
            $this->fail('Expected a controlled hospital integration exception.');
        } catch (HospitalIntegrationException $exception) {
            $this->assertStringContainsString($safeMessageFragment, $exception->safeMessage);
            $this->assertStringNotContainsString('test-secret-token', $exception->safeMessage);
        }
    }
}
