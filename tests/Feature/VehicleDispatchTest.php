<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDispatch;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;
    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $dept = Department::create(['name' => '業務部', 'code' => 'SALES']);
        $this->user = User::factory()->create([
            'department_id' => $dept->id,
            'role' => 'employee',
        ]);

        $this->admin = User::factory()->create([
            'department_id' => $dept->id,
            'role' => 'admin',
        ]);

        $this->vehicle = Vehicle::create([
            'plate_number' => 'ABC-8888',
            'name' => 'Toyota Camry Hybrid',
            'vehicle_type' => 'sedan',
            'capacity' => 5,
            'fuel_type' => 'hybrid',
            'current_mileage' => 12500,
            'status' => 'available',
        ]);
    }

    public function test_user_can_view_vehicle_dispatch_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('vehicles.index'));
        $response->assertStatus(200);
    }

    public function test_user_can_submit_vehicle_dispatch_request(): void
    {
        $startTime = Carbon::now()->addDays(2)->setHour(9)->setMinute(0);
        $endTime = Carbon::now()->addDays(2)->setHour(18)->setMinute(0);

        $response = $this->actingAs($this->user)->post(route('vehicles.store'), [
            'vehicle_id' => $this->vehicle->id,
            'destination' => '新竹科學園區台積電 12 廠',
            'purpose' => 'client_visit',
            'start_time' => $startTime->toDateTimeString(),
            'end_time' => $endTime->toDateTimeString(),
            'passenger_count' => 3,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vehicle_dispatches', [
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->user->id,
            'destination' => '新竹科學園區台積電 12 廠',
            'status' => 'pending',
            'passenger_count' => 3,
        ]);
    }

    public function test_system_prevents_overlapping_vehicle_booking(): void
    {
        $startTime = Carbon::now()->addDays(3)->setHour(10)->setMinute(0);
        $endTime = Carbon::now()->addDays(3)->setHour(15)->setMinute(0);

        // 已有一筆已核准派車單
        VehicleDispatch::create([
            'dispatch_number' => 'VD-20261015-0001',
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->admin->id,
            'destination' => '台中精密機械園區',
            'purpose' => 'client_visit',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'passenger_count' => 2,
            'status' => 'approved',
        ]);

        // 第二位同仁嘗試借調重疊時段 (11:00 - 16:00)
        $overlapStart = Carbon::now()->addDays(3)->setHour(11)->setMinute(0);
        $overlapEnd = Carbon::now()->addDays(3)->setHour(16)->setMinute(0);

        $response = $this->actingAs($this->user)->post(route('vehicles.store'), [
            'vehicle_id' => $this->vehicle->id,
            'destination' => '桃園國際機場接機',
            'purpose' => 'airport_pickup',
            'start_time' => $overlapStart->toDateTimeString(),
            'end_time' => $overlapEnd->toDateTimeString(),
            'passenger_count' => 2,
        ]);

        $response->assertSessionHasErrors('vehicle_id');
    }

    public function test_manager_can_approve_and_reject_dispatch(): void
    {
        $dispatch = VehicleDispatch::create([
            'dispatch_number' => 'VD-20261016-0002',
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->user->id,
            'destination' => '高雄軟體科學園區',
            'purpose' => 'client_visit',
            'start_time' => Carbon::now()->addDays(4)->setHour(8)->setMinute(0),
            'end_time' => Carbon::now()->addDays(4)->setHour(20)->setMinute(0),
            'passenger_count' => 4,
            'status' => 'pending',
        ]);

        // 核准
        $response = $this->actingAs($this->admin)->post(route('vehicles.approve', $dispatch->id));
        $response->assertRedirect();
        $this->assertEquals('approved', $dispatch->fresh()->status);
        $this->assertEquals($this->admin->id, $dispatch->fresh()->approved_by);

        // 建立另一筆進行駁回測試
        $dispatchToReject = VehicleDispatch::create([
            'dispatch_number' => 'VD-20261016-0003',
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->user->id,
            'destination' => '花蓮服務處',
            'purpose' => 'client_visit',
            'start_time' => Carbon::now()->addDays(5)->setHour(8)->setMinute(0),
            'end_time' => Carbon::now()->addDays(5)->setHour(20)->setMinute(0),
            'passenger_count' => 2,
            'status' => 'pending',
        ]);

        // 駁回
        $response = $this->actingAs($this->admin)->post(route('vehicles.reject', $dispatchToReject->id), [
            'rejection_reason' => '當日總經理臨時需要使用該公務車出訪',
        ]);
        $response->assertRedirect();
        $this->assertEquals('rejected', $dispatchToReject->fresh()->status);
        $this->assertEquals('當日總經理臨時需要使用該公務車出訪', $dispatchToReject->fresh()->rejection_reason);
    }

    public function test_start_and_return_trip_updates_mileage(): void
    {
        $dispatch = VehicleDispatch::create([
            'dispatch_number' => 'VD-20261017-0003',
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->user->id,
            'destination' => '宜蘭研發園區',
            'purpose' => 'vendor_meeting',
            'start_time' => Carbon::now()->addDay()->setHour(9)->setMinute(0),
            'end_time' => Carbon::now()->addDay()->setHour(18)->setMinute(0),
            'passenger_count' => 2,
            'status' => 'approved',
            'start_mileage' => 12500,
        ]);

        // 領車出發
        $response = $this->actingAs($this->admin)->post(route('vehicles.start', $dispatch->id), [
            'start_mileage' => 12500,
        ]);
        $response->assertRedirect();
        $this->assertEquals('active', $dispatch->fresh()->status);
        $this->assertEquals('in_use', $this->vehicle->fresh()->status);
        $this->assertEquals(12500, $dispatch->fresh()->start_mileage);

        // 還車回報
        $response = $this->actingAs($this->admin)->post(route('vehicles.return', $dispatch->id), [
            'end_mileage' => 12680,
            'return_notes' => '已在蘇澳加油站加滿油，車輛完好',
        ]);
        $response->assertRedirect();
        $this->assertEquals('completed', $dispatch->fresh()->status);
        $this->assertEquals('available', $this->vehicle->fresh()->status);
        $this->assertEquals(12680, $this->vehicle->fresh()->current_mileage);
        $this->assertEquals(180, $dispatch->fresh()->mileage_traveled);
    }

    public function test_user_can_cancel_own_dispatch(): void
    {
        $dispatch = VehicleDispatch::create([
            'dispatch_number' => 'VD-20261018-0004',
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->user->id,
            'destination' => '台北市政府',
            'purpose' => 'other',
            'start_time' => Carbon::now()->addDays(5)->setHour(10)->setMinute(0),
            'end_time' => Carbon::now()->addDays(5)->setHour(12)->setMinute(0),
            'passenger_count' => 1,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)->post(route('vehicles.cancel', $dispatch->id));
        $response->assertRedirect();
        $this->assertEquals('cancelled', $dispatch->fresh()->status);
    }

    public function test_manager_can_export_vehicle_dispatch_csv(): void
    {
        $response = $this->actingAs($this->admin)->get(route('vehicles.export'));
        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'text/csv'));
    }
}
