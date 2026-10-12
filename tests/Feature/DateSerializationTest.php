<?php

namespace Tests\Feature;

use App\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_columns_serialize_with_local_offset_so_front_end_slice_is_correct(): void
    {
        $asset = Asset::create([
            'asset_tag' => 'AST-TZ-1',
            'name' => '時區測試資產',
            'category' => 'it_hardware',
            'status' => 'available',
            'purchase_date' => '2026-02-12',
        ]);

        $json = $asset->fresh()->toArray();

        // 前端以 substring(0, 10) 取日期:必須仍是 2026-02-12,而不是 UTC 換算後的 2026-02-11
        $this->assertSame('2026-02-12', substr($json['purchase_date'], 0, 10));
        $this->assertStringEndsWith('+08:00', $json['purchase_date']);
        $this->assertSame('2026-02-12', substr(json_decode($asset->fresh()->toJson(), true)['purchase_date'], 0, 10));
    }
}
