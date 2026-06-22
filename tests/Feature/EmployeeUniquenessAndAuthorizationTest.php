<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EmployeeUniquenessAndAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    /**
     * Test database unique index on identification.
     */
    public function test_database_prevents_duplicate_identifications(): void
    {
        $uniqueId = 'TEST_DNI_' . uniqid();

        // Create first employee
        Employee::create([
            'identification' => $uniqueId,
            'name' => 'First Employee',
            'approval_status' => 'Revisión'
        ]);

        // Attempt to create second employee with same identification
        $this->expectException(QueryException::class);
        
        Employee::create([
            'identification' => $uniqueId,
            'name' => 'Second Employee',
            'approval_status' => 'Revisión'
        ]);
    }

    /**
     * Test Voyager validation fails for duplicate DNI on create.
     */
    public function test_voyager_validation_prevents_duplicate_dni(): void
    {
        // Find an admin user to make the request
        $admin = User::whereHas('role', function ($query) {
            $query->where('name', 'admin')->orWhere('name', 'tech_admin');
        })->first();

        $this->assertNotNull($admin, 'An admin or tech_admin user must exist in the database for this test.');

        $uniqueId = 'TEST_DNI_' . uniqid();

        // Create first employee
        Employee::create([
            'identification' => $uniqueId,
            'name' => 'First Employee',
            'approval_status' => 'Revisión'
        ]);

        // Act as admin and try to create another employee with same DNI via store route
        $response = $this->actingAs($admin)
            ->from(route('voyager.employees.create'))
            ->post(route('voyager.employees.store'), [
                'name' => 'Second Employee',
                'identification' => $uniqueId,
                'approval_status' => 'Revisión'
            ]);

        // Should redirect back with validation errors
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['identification']);
    }

    /**
     * Test supplier user can only edit allowed fields and forbidden fields are ignored.
     */
    public function test_supplier_user_field_restrictions(): void
    {
        // Find a supplier user
        $supplierUser = User::whereHas('role', function ($query) {
            $query->where('name', 'supplier');
        })->first();

        $this->assertNotNull($supplierUser, 'A supplier user must exist in the database for this test.');

        // Find or create a supplier belonging to this user
        $supplier = Supplier::where('user_id', $supplierUser->id)->first();
        if (!$supplier) {
            $supplier = Supplier::create([
                'name' => 'Test Supplier',
                'user_id' => $supplierUser->id
            ]);
        }

        // Create an employee belonging to this supplier
        $employee = Employee::create([
            'identification' => 'TEST_SUP_' . uniqid(),
            'name' => 'Original Name',
            'condition' => 'Empleado',
            'cost_center' => 'Original Center',
            'approval_status' => 'Revisión',
            'validity_from' => '2026-06-20',
            'validity_to' => '2026-06-25',
            'supplier_id' => $supplier->id
        ]);

        // Act as the supplier user and send a PUT request to update
        $response = $this->actingAs($supplierUser)
            ->put(route('voyager.employees.update', $employee->id), [
                // Allowed fields (modified)
                'name' => 'Modified Name',
                'identification' => $employee->identification,
                'condition' => 'Autónomo',
                'cost_center' => 'Modified Center',
                'supplier_id' => $supplier->id,
                // Forbidden fields (attempting to modify)
                'approval_status' => 'Aprobado',
                'validity_from' => '2026-07-01',
                'validity_to' => '2026-07-31',
            ]);

        $response->assertStatus(302);

        // Refresh employee from database
        $employee->refresh();

        // Assert allowed fields WERE updated
        $this->assertEquals('Modified Name', $employee->name);
        $this->assertEquals('Autónomo', $employee->condition);
        $this->assertEquals('Modified Center', $employee->cost_center);

        // Assert forbidden fields WERE NOT updated (they should be either ignored or default)
        $this->assertEquals('Revisión', $employee->approval_status);
        $this->assertEquals('2026-06-20', $employee->validity_from->format('Y-m-d'));
        $this->assertEquals('2026-06-25', $employee->validity_to->format('Y-m-d'));
    }

    /**
     * Test supplier user cannot assign a supplier_id belonging to another user.
     */
    public function test_supplier_user_cannot_assign_other_supplier(): void
    {
        // Find a supplier user
        $supplierUser = User::whereHas('role', function ($query) {
            $query->where('name', 'supplier');
        })->first();

        $this->assertNotNull($supplierUser, 'A supplier user must exist in the database for this test.');

        // Find or create a supplier belonging to this user
        $supplier = Supplier::where('user_id', $supplierUser->id)->first();
        if (!$supplier) {
            $supplier = Supplier::create([
                'name' => 'Test Supplier',
                'user_id' => $supplierUser->id
            ]);
        }

        // Create another supplier belonging to a different user
        $otherSupplier = Supplier::create([
            'name' => 'Other Supplier',
            'user_id' => 999999
        ]);

        // Create an employee belonging to the user's supplier
        $employee = Employee::create([
            'identification' => 'TEST_SUP_ERR_' . uniqid(),
            'name' => 'Original Name',
            'supplier_id' => $supplier->id
        ]);

        // Act as supplier user and try to change employee's supplier to $otherSupplier
        $response = $this->actingAs($supplierUser)
            ->put(route('voyager.employees.update', $employee->id), [
                'name' => 'Modified Name',
                'identification' => $employee->identification,
                'supplier_id' => $otherSupplier->id,
            ]);

        // Should return 403 Forbidden
        $response->assertStatus(403);

        // Verify that the supplier_id was NOT changed
        $employee->refresh();
        $this->assertEquals($supplier->id, $employee->supplier_id);
    }

    /**
     * Test supplier user can create an employee and supplier_id is automatically assigned.
     */
    public function test_supplier_user_can_create_employee_with_auto_supplier_id(): void
    {
        // Find a supplier user
        $supplierUser = User::whereHas('role', function ($query) {
            $query->where('name', 'supplier');
        })->first();

        $this->assertNotNull($supplierUser, 'A supplier user must exist in the database for this test.');

        // Find or create a supplier belonging to this user
        $supplier = Supplier::where('user_id', $supplierUser->id)->first();
        if (!$supplier) {
            $supplier = Supplier::create([
                'name' => 'Test Supplier',
                'user_id' => $supplierUser->id
            ]);
        }

        $uniqueId = 'TEST_AUTO_' . uniqid();

        // Act as supplier user and send a POST request to store (without supplier_id)
        $response = $this->actingAs($supplierUser)
            ->post(route('voyager.employees.store'), [
                'name' => 'Auto Employee',
                'identification' => $uniqueId,
                'condition' => 'Empleado',
            ]);

        $response->assertStatus(302);

        // Find the created employee
        $employee = Employee::where('identification', $uniqueId)->first();
        $this->assertNotNull($employee);
        $this->assertEquals('Auto Employee', $employee->name);
        // Verify that the supplier_id was automatically assigned!
        $this->assertEquals($supplier->id, $employee->supplier_id);
    }
}
