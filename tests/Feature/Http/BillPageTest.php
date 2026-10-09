<?php

/**
 * BillPageTest.php
 *
 * The bill page with its graph, transfer, history, edit, delete and reset views.
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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BillPageTest extends TestCase
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

    private function quotaBill(): Bill
    {
        return Bill::factory()->create([
            'bill_name' => 'Acme quota',
            'bill_type' => 'quota',
            'bill_quota' => 500000000000,
            'bill_cdr' => 0,
            'bill_day' => 1,
            'total_data' => 300000000000,
            'total_data_in' => 100000000000,
            'total_data_out' => 200000000000,
            'rate_average' => 12345678,
        ]);
    }

    public function testQuickAndAccurateViewsRender(): void
    {
        $bill = $this->quotaBill();
        $device = Device::factory()->create();
        $port = Port::factory()->create(['device_id' => $device->device_id, 'ifName' => 'billport7', 'ifDescr' => 'billport7', 'ifAlias' => '']);
        $bill->ports()->attach($port->port_id);

        $this->actingAs($this->admin())->get(route('bill.show', $bill))
            ->assertOk()
            ->assertSee('Acme quota')
            ->assertSee(__('Quota Bill'))
            ->assertSee('300 GB')
            ->assertSee('500 GB')
            ->assertSee('billport7')
            ->assertSee('type=bill_bits', false)
            ->assertSee(__('Edit'))
            ->assertSee(__('Delete'));

        $this->actingAs($this->admin())->get(route('bill.show', ['bill' => $bill, 'view' => 'accurate', 'bill_code' => 'x']))
            ->assertOk()
            ->assertSee('type=bill_historicbits', false);
    }

    public function testTransferViewRenders(): void
    {
        $bill = $this->quotaBill();

        $this->actingAs($this->admin())->get(route('bill.show', ['bill' => $bill, 'view' => 'transfer']))
            ->assertOk()
            ->assertSee(__('Transfer Report'))
            ->assertSee(__('Inbound'))
            ->assertSee('100 GB')
            ->assertSee('type=bill_historictransfer', false);
    }

    public function testHistoryViewRendersPeriodsAndDetails(): void
    {
        $bill = $this->quotaBill();
        $entry = BillHistory::create([
            'bill_id' => $bill->bill_id,
            'bill_datefrom' => '2026-08-01 00:00:00',
            'bill_dateto' => '2026-08-31 23:59:59',
            'bill_type' => 'Quota',
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

        $this->actingAs($this->admin())->get(route('bill.show', ['bill' => $bill, 'view' => 'history']))
            ->assertOk()
            ->assertSee('2026-08-01 to 2026-08-31')
            ->assertSee('2 kB')
            ->assertSee('type=bill_historicmonthly', false)
            ->assertDontSee('bill_hist_id=' . $entry->bill_hist_id, false);

        // legacy link format with the detail of one period
        $this->actingAs($this->admin())->get('bill/bill_id=' . $bill->bill_id . '/view=history/detail=' . $entry->bill_hist_id . '/')
            ->assertOk()
            ->assertSee('bill_hist_id=' . $entry->bill_hist_id, false);
    }

    public function testEditViewShowsTheFormAndPorts(): void
    {
        $bill = $this->quotaBill();
        $device = Device::factory()->create();
        $port = Port::factory()->create(['device_id' => $device->device_id, 'ifName' => 'billport1', 'ifDescr' => 'billport1', 'ifAlias' => '']);
        $bill->ports()->attach($port->port_id);

        $this->actingAs($this->admin())->get(route('bill.show', ['bill' => $bill, 'view' => 'edit']))
            ->assertOk()
            ->assertSee(route('bill.update', $bill), false)
            ->assertSee('value="Acme quota"', false)
            ->assertSee('value="500"', false) // 500 GB
            ->assertSee('billport1')
            ->assertSee(route('bill.port.detach', [$bill, $port]), false)
            ->assertSee(route('bill.port.attach', $bill), false);
    }

    public function testDeleteAndResetViewsRender(): void
    {
        $bill = $this->quotaBill();

        $this->actingAs($this->admin())->get(route('bill.show', ['bill' => $bill, 'view' => 'delete']))
            ->assertOk()
            ->assertSee(route('bill.destroy', $bill), false);

        $this->actingAs($this->admin())->get(route('bill.show', ['bill' => $bill, 'view' => 'reset']))
            ->assertOk()
            ->assertSee(route('bill.reset', $bill), false);
    }

    public function testUnknownViewIsNotFound(): void
    {
        $this->actingAs($this->admin())->get(route('bill.show', ['bill' => $this->quotaBill(), 'view' => 'nope']))->assertNotFound();
    }

    public function testViewerCannotOpenTheManagementViews(): void
    {
        $bill = $this->quotaBill();
        $user = User::factory()->create(['enabled' => 1]);
        $user->assignRole('user');
        $user->givePermissionTo('bill.view');
        BillPerm::query()->insert(['user_id' => $user->user_id, 'bill_id' => $bill->bill_id]);

        $this->actingAs($user)->get(route('bill.show', $bill))
            ->assertOk()
            ->assertDontSee(route('bill.show', ['bill' => $bill, 'view' => 'edit']), false);

        foreach (['edit', 'delete', 'reset'] as $view) {
            $this->actingAs($user)->get(route('bill.show', ['bill' => $bill, 'view' => $view]))->assertForbidden();
        }
    }

    public function testUserWithoutAccessToTheBillIsForbidden(): void
    {
        $bill = $this->quotaBill();
        $user = User::factory()->create(['enabled' => 1]);
        $user->assignRole('user');
        $user->givePermissionTo('bill.view');

        $this->actingAs($user)->get(route('bill.show', $bill))->assertForbidden();
    }
}
