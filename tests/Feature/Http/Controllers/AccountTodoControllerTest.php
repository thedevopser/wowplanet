<?php

declare(strict_types=1);

use App\Application\Services\AccountTodoService;

test('the list of what is left is computed under the BattleTag of the session', function (): void {
    $mock = $this->mock(AccountTodoService::class);
    $mock->shouldReceive('getOrCompute')->once()->with('Thrall#1234')
        ->andReturn(['status' => 'computing', 'step' => 'characters', 'jobId' => 'job-1']);

    $this->withSession(['blizzard_user_token' => 'fake-token', 'bnet_battletag' => 'Thrall#1234'])
        ->getJson('/api/account/todo')
        ->assertOk()
        ->assertExactJson(['status' => 'computing', 'step' => 'characters', 'jobId' => 'job-1']);
});

test('the list of what is left is refused to a visitor who is not signed in', function (): void {
    $mock = $this->mock(AccountTodoService::class);
    $mock->shouldReceive('getOrCompute')->once()->with('')->andReturn(['status' => 'unauthenticated']);

    $this->getJson('/api/account/todo')->assertUnauthorized();
});

test('a cross-character computation that failed is answered as a server error', function (): void {
    $mock = $this->mock(AccountTodoService::class);
    $mock->shouldReceive('getOrCompute')->once()->andReturn(['status' => 'failed']);

    $this->withSession(['blizzard_user_token' => 'fake-token'])
        ->getJson('/api/account/todo')
        ->assertStatus(502)
        ->assertExactJson(['status' => 'failed']);
});

test('refreshing the list drops its computations for the signed-in player', function (): void {
    $mock = $this->mock(AccountTodoService::class);
    $mock->shouldReceive('refresh')->once();

    $this->withSession(['blizzard_user_token' => 'fake-token', 'bnet_user_id' => '42'])
        ->postJson('/api/account/todo/refresh')
        ->assertOk()
        ->assertExactJson(['success' => true]);
});

test('refreshing the list is refused to a visitor who is not signed in', function (): void {
    $mock = $this->mock(AccountTodoService::class);
    $mock->shouldNotReceive('refresh');

    $this->postJson('/api/account/todo/refresh')->assertUnauthorized();
});
