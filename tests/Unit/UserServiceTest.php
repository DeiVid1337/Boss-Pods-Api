<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Services\UserService;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    private UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = new UserService();
    }

    public function test_list_applies_search_filter_on_name_email(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@boss.local']);
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);
        User::factory()->create(['name' => 'Bob Johnson', 'email' => 'bob@test.com']);

        $result = $this->userService->list($admin, ['search' => 'example'], 15);

        $this->assertCount(2, $result->items());
    }

    public function test_list_applies_sort_by_email_asc(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'zzz@example.com']);
        User::factory()->create(['email' => 'zebra@example.com']);
        User::factory()->create(['email' => 'alpha@example.com']);
        User::factory()->create(['email' => 'beta@example.com']);

        $result = $this->userService->list($admin, ['sort_by' => 'email', 'sort_order' => 'asc'], 15);

        $items = $result->items();
        $this->assertEquals('alpha@example.com', $items[0]->email);
        $this->assertEquals('beta@example.com', $items[1]->email);
        $this->assertEquals('zebra@example.com', $items[2]->email);
        $this->assertEquals('zzz@example.com', $items[3]->email);
    }

    public function test_list_defaults_to_name_asc_when_invalid_sort_by(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@boss.local', 'name' => 'Zzz Admin']);
        User::factory()->create(['name' => 'Zebra']);
        User::factory()->create(['name' => 'Alpha']);

        $result = $this->userService->list($admin, ['sort_by' => 'invalid'], 15);

        $items = $result->items();
        $this->assertEquals('Alpha', $items[0]->name);
    }
}
