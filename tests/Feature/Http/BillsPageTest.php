<?php

/**
 * BillsPageTest.php
 *
 * The traffic bills list page, its table endpoint and the create dialog.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 */

namespace LibreNMS\Tests\Feature\Http;

use App\Facades\LibrenmsConfig;
use App\Models\Bill;
use App\Models\BillHistory;
use App\Models\BillPerm;
use App\Models\Device;
use App\Models\Port;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LibreNMS\Tests\TestCase;
use LibreNMS\Util\Url;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BillsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        Role::findOrCreate('user');
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::findOrCreate("bill.$action");
        }

        LibrenmsConfig::set('enable_billing', 1);
        LibrenmsConfig::set('billing.base', 1000);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['enabled' => 1]);
        $admin->assignRole('admin');

        return $admin;
    }

    private function user(string ...$permissions): User
    {
        $user = User::factory()->create(['enabled' => 1]);
        $user->assignRole('user');
        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    public function testAdminCanRenderBillsPage(): void
    {
        $this->actingAs($this->admin())->get(route('bills.index'))
            ->assertOk()
            ->assertSee(route('table.bills'), false)
            ->assertSee(__('Create Bill'))
            ->assertSee(route('bill.store'), false);
    }

    public function testUserWithoutCreatePermissionGetsNoCreateDialog(): void
    {
        $this->actingAs($this->user('bill.viewAny'))->get(route('bills.index'))
            ->assertOk()
            ->assertDontSee(__('Create Bill'))
            ->assertDontSee('create-bill-form', false);
    }

    public function testBillingDisabledHidesThePage(): void
    {
        LibrenmsConfig::set('enable_billing', 0);

        $this->actingAs($this->user('bill.viewAny'))->get(route('bills.index'))->assertForbidden();
    }

    public function testLegacyAddLinkOpensTheDialogWithThePort(): void
    {
        $device = Device::factory()->create();
        $port = Port::factory()->create(['device_id' => $device->device_id, 'ifAlias' => 'Customer uplink', 'port_descr_descr' => 'Acme transit']);

        $this->actingAs($this->admin())->get('bills/view=add/port=' . $port->port_id)
            ->assertOk()
            ->assertSee('createModalOpen: true', false)
            ->assertSee('Customer uplink')
            ->assertSee('value="Acme transit"', false);
    }

    public function testTableListsBillsOfTheCurrentPeriodWithFilters(): void
    {
        $over = Bill::factory()->create(['bill_name' => 'Over CDR', 'bill_type' => 'cdr', 'bill_cdr' => 1000, 'rate_95th' => 2000, 'bill_day' => 1]);
        $under = Bill::factory()->create(['bill_name' => 'Under Quota', 'bill_type' => 'quota', 'bill_quota' => 5000, 'total_data' => 1000, 'bill_day' => 1]);

        $rows = fn (array $filters = []) => $this->actingAs($this->admin())
            ->postJson(route('table.bills'), $filters + ['current' => 1, 'rowCount' => 50, 'searchPhrase' => ''])
            ->assertOk()
            ->json();

        $all = $rows();
        $this->assertEquals(2, $all['total']);
        $this->assertStringContainsString('Over CDR', $all['rows'][0]['bill_name']);
        $this->assertStringContainsString(Url::generate(['page' => 'bill', 'bill_id' => $over->bill_id]), $all['rows'][0]['bill_name']);
        $this->assertEquals('CDR', $all['rows'][0]['bill_type']);
        $this->assertStringContainsString('1 kbps', $all['rows'][0]['overusage']);
        $this->assertEquals('-', $all['rows'][1]['overusage']);
        $this->assertStringContainsString('Edit', $all['rows'][0]['actions']);

        $this->assertEquals(1, $rows(['bill_type' => 'quota'])['total']);
        $this->assertStringContainsString('Under Quota', $rows(['bill_type' => 'quota'])['rows'][0]['bill_name']);
        $this->assertEquals(1, $rows(['state' => 'over'])['total']);
        $this->assertStringContainsString('Over CDR', $rows(['state' => 'over'])['rows'][0]['bill_name']);
        $this->assertEquals(1, $rows(['state' => 'under'])['total']);
        $this->assertEquals(1, $rows(['searchPhrase' => 'Quota'])['total']);
    }

    public function testTableShowsThePreviousPeriodFromHistory(): void
    {
        $bill = Bill::factory()->create(['bill_name' => 'Historic', 'bill_type' => 'quota', 'bill_quota' => 1, 'total_data' => 0, 'bill_day' => 1]);
        Bill::factory()->create(['bill_name' => 'No history', 'bill_type' => 'quota', 'bill_quota' => 1, 'bill_day' => 1]);
        BillHistory::create([
            'bill_id' => $bill->bill_id,
            'bill_datefrom' => now()->subDays(31),
            'bill_dateto' => now()->subDay(),
            'bill_type' => 'quota',
            'bill_allowed' => 1000,
            'bill_used' => 3000,
            'bill_overuse' => 2000,
            'bill_percent' => 300,
            'rate_95th_in' => 0,
            'rate_95th_out' => 0,
            'rate_95th' => 0,
            'dir_95th' => 'in',
            'rate_average' => 0,
            'rate_average_in' => 0,
            'rate_average_out' => 0,
            'traf_in' => 1000,
            'traf_out' => 2000,
            'traf_total' => 3000,
            'bill_peak_out' => 0,
            'bill_peak_in' => 0,
        ]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('table.bills'), ['period' => 'prev', 'current' => 1, 'rowCount' => 50, 'searchPhrase' => ''])
            ->assertOk();

        $response->assertJsonPath('total', 1);
        $this->assertStringContainsString('Historic', $response->json('rows.0.bill_name'));
        $this->assertStringContainsString('3 kB', $response->json('rows.0.total_data'));
        $this->assertStringContainsString('2 kB', $response->json('rows.0.overusage'));
        $this->assertSame('', $response->json('rows.0.actions'), 'a closed period is not editable');
    }

    public function testUserOnlySeesPermittedBills(): void
    {
        $mine = Bill::factory()->create(['bill_name' => 'Mine', 'bill_type' => 'quota', 'bill_quota' => 1, 'bill_day' => 1]);
        Bill::factory()->create(['bill_name' => 'Not mine', 'bill_type' => 'quota', 'bill_quota' => 1, 'bill_day' => 1]);
        $user = $this->user('bill.viewAny');
        BillPerm::query()->insert(['user_id' => $user->user_id, 'bill_id' => $mine->bill_id]);

        $response = $this->actingAs($user)
            ->postJson(route('table.bills'), ['current' => 1, 'rowCount' => 50, 'searchPhrase' => ''])
            ->assertOk();

        $response->assertJsonPath('total', 1);
        $this->assertStringContainsString('Mine', $response->json('rows.0.bill_name'));
    }

    public function testAdminCanCreateBillWithPort(): void
    {
        $device = Device::factory()->create();
        $port = Port::factory()->create(['device_id' => $device->device_id]);

        $response = $this->actingAs($this->admin())->post(route('bill.store'), [
            'bill_name' => 'New quota bill',
            'bill_type' => 'quota',
            'bill_quota' => 500,
            'bill_quota_type' => 'MB',
            'bill_day' => 15,
            'bill_custid' => 'CUST-1',
            'port_id' => $port->port_id,
        ]);

        $bill = Bill::where('bill_name', 'New quota bill')->first();
        $this->assertNotNull($bill);
        $response->assertRedirect(Url::generate(['page' => 'bill', 'bill_id' => $bill->bill_id, 'view' => 'edit']));
        $this->assertEquals(500000000, $bill->bill_quota);
        $this->assertEquals(0, $bill->bill_cdr);
        $this->assertEquals(15, $bill->bill_day);
        $this->assertEquals('CUST-1', $bill->bill_custid);
        $this->assertTrue($bill->ports()->where('ports.port_id', $port->port_id)->exists());
    }

    public function testCreateValidatesInput(): void
    {
        $this->actingAs($this->admin())->from(route('bills.index'))->post(route('bill.store'), [
            'bill_type' => 'cdr',
            'bill_day' => 40,
            'port_id' => 999999,
        ])->assertRedirect(route('bills.index'))
            ->assertSessionHasErrors(['bill_name', 'bill_day', 'port_id']);

        $this->assertEquals(0, Bill::count());
    }

    public function testUserWithoutCreatePermissionCannotCreate(): void
    {
        $this->actingAs($this->user('bill.viewAny'))->post(route('bill.store'), [
            'bill_name' => 'Nope',
            'bill_type' => 'cdr',
            'bill_day' => 1,
        ])->assertForbidden();

        $this->assertEquals(0, Bill::count());
    }
}
