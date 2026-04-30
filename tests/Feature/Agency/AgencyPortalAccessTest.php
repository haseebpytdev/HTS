<?php

namespace Tests\Feature\Agency;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_user_only_sees_own_quotation_data(): void
    {
        $agencyA = Agency::create(['name' => 'Agency A', 'code' => 'A001', 'is_active' => true]);
        $agencyB = Agency::create(['name' => 'Agency B', 'code' => 'B001', 'is_active' => true]);

        $agencyUserA = User::create([
            'name' => 'Agency User A',
            'email' => 'agencya@test.local',
            'password' => 'password',
            'role' => UserRole::AGENCY_USER->value,
            'agency_id' => $agencyA->id,
        ]);

        $agencyUserB = User::create([
            'name' => 'Agency User B',
            'email' => 'agencyb@test.local',
            'password' => 'password',
            'role' => UserRole::AGENCY_USER->value,
            'agency_id' => $agencyB->id,
        ]);

        $quoteA = Quotation::create([
            'agency_id' => $agencyA->id,
            'user_id' => $agencyUserA->id,
            'quote_number' => 'QTN-2099-0001',
            'customer_name' => 'Customer A',
            'currency' => 'PKR',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'subtotal' => 1000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 1000,
            'status' => 'draft',
        ]);

        $quoteB = Quotation::create([
            'agency_id' => $agencyB->id,
            'user_id' => $agencyUserB->id,
            'quote_number' => 'QTN-2099-0002',
            'customer_name' => 'Customer B',
            'currency' => 'PKR',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'subtotal' => 1000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 1000,
            'status' => 'draft',
        ]);

        $this->actingAs($agencyUserA)
            ->get(route('agency.quotations.index'))
            ->assertOk()
            ->assertSee($quoteA->quote_number)
            ->assertDontSee($quoteB->quote_number);

        $this->actingAs($agencyUserA)
            ->get(route('agency.quotations.show', $quoteB))
            ->assertForbidden();
    }

    public function test_agency_user_only_sees_own_inquiries_on_portal(): void
    {
        $agencyA = Agency::create(['name' => 'Agency Inq A', 'code' => 'INA'.substr(uniqid(), -5), 'is_active' => true]);
        $agencyB = Agency::create(['name' => 'Agency Inq B', 'code' => 'INB'.substr(uniqid(), -5), 'is_active' => true]);

        $userA = User::factory()->create([
            'role' => UserRole::AGENCY_USER->value,
            'agency_id' => $agencyA->id,
        ]);

        Inquiry::create([
            'source' => 'package',
            'agency_id' => $agencyA->id,
            'name' => 'Lead A',
            'email' => 'a@inq.test',
            'adults' => 2,
            'children' => 0,
            'currency' => 'PKR',
            'message' => 'Hello A',
            'status' => Inquiry::STATUS_NEW,
        ]);

        Inquiry::create([
            'source' => 'package',
            'agency_id' => $agencyB->id,
            'name' => 'Lead B',
            'email' => 'b@inq.test',
            'adults' => 1,
            'children' => 0,
            'currency' => 'PKR',
            'message' => 'Hello B',
            'status' => Inquiry::STATUS_NEW,
        ]);

        $this->actingAs($userA)
            ->get(route('agency.inquiries.index'))
            ->assertOk()
            ->assertSee('Lead A')
            ->assertDontSee('Lead B');
    }

    public function test_agency_user_cannot_post_revision_against_other_agency_quotation(): void
    {
        $agencyA = Agency::create(['name' => 'Agency Rev A', 'code' => 'RVA'.substr(uniqid(), -5), 'is_active' => true]);
        $agencyB = Agency::create(['name' => 'Agency Rev B', 'code' => 'RVB'.substr(uniqid(), -5), 'is_active' => true]);

        $userA = User::factory()->create([
            'role' => UserRole::AGENCY_USER->value,
            'agency_id' => $agencyA->id,
        ]);

        $quoteB = Quotation::create([
            'agency_id' => $agencyB->id,
            'user_id' => $userA->id,
            'quote_number' => 'QTN-REV-B-'.uniqid(),
            'customer_name' => 'Other Agency Customer',
            'currency' => 'PKR',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 500,
            'status' => 'draft',
        ]);

        $this->actingAs($userA)
            ->post(route('agency.quotations.revision', $quoteB), [
                'message' => 'Please change hotel nights to seven instead.',
            ])
            ->assertForbidden();
    }
}
