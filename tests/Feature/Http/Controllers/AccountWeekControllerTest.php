<?php

declare(strict_types=1);

use App\Application\Services\WeeklyActivityService;

test('the week is computed under the BattleTag of the session', function (): void {
    $mock = $this->mock(WeeklyActivityService::class);
    $mock->shouldReceive('getOrCompute')->once()->with('Thrall#1234')
        ->andReturn(['status' => 'computing', 'jobId' => 'job-1']);

    $this->withSession(['blizzard_user_token' => 'fake-token', 'bnet_battletag' => 'Thrall#1234'])
        ->getJson('/api/account/week')
        ->assertOk()
        ->assertExactJson(['status' => 'computing', 'jobId' => 'job-1']);
});

test('the week is refused to a visitor who is not signed in', function (): void {
    $mock = $this->mock(WeeklyActivityService::class);
    $mock->shouldReceive('getOrCompute')->once()->with('')->andReturn(['status' => 'unauthenticated']);

    $this->getJson('/api/account/week')->assertUnauthorized();
});

test('a weekly computation that failed is answered as a server error', function (): void {
    $mock = $this->mock(WeeklyActivityService::class);
    $mock->shouldReceive('getOrCompute')->once()->andReturn(['status' => 'failed']);

    $this->withSession(['blizzard_user_token' => 'fake-token'])
        ->getJson('/api/account/week')
        ->assertStatus(502)
        ->assertExactJson(['status' => 'failed']);
});

test('refreshing the week drops it for the signed-in player', function (): void {
    $mock = $this->mock(WeeklyActivityService::class);
    $mock->shouldReceive('refresh')->once();

    $this->withSession(['blizzard_user_token' => 'fake-token', 'bnet_user_id' => '42'])
        ->postJson('/api/account/week/refresh')
        ->assertOk()
        ->assertExactJson(['success' => true]);
});

test('refreshing the week is refused to a visitor who is not signed in', function (): void {
    $mock = $this->mock(WeeklyActivityService::class);
    $mock->shouldNotReceive('refresh');

    $this->postJson('/api/account/week/refresh')->assertUnauthorized();
});
