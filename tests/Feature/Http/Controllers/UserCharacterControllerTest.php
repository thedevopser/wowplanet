<?php

declare(strict_types=1);

use App\Application\Services\UserCharacterService;

test('the auth state is no longer served by a dedicated endpoint', function (): void {
    $this->getJson('/api/auth/status')->assertNotFound();
});

test('logout clears session', function (): void {
    $mock = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('logout');
    $exp->once();

    $this->postJson('/api/auth/logout')->assertOk();
});

test('index returns 401 when not authenticated', function (): void {
    $mock = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('isAuthenticated');
    $exp->once()->andReturn(false);

    $this->getJson('/api/user/characters')->assertUnauthorized();
});

test('class icons returns json structure', function (): void {
    $mock = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('getClassIcons');
    $exp->once()->andReturn([
        1 => 'https://example.com/warrior.jpg',
        2 => 'https://example.com/paladin.jpg',
    ]);

    $this->getJson('/api/class-icons')
        ->assertOk()
        ->assertJsonCount(2);
});

test('index returns characters when authenticated', function (): void {
    $mock = $this->mock(UserCharacterService::class);
    $mock->shouldReceive('isAuthenticated')->once()->andReturn(true);
    $mock->shouldReceive('getUserCharacters')->once()->andReturn([
        ['name' => 'Thrall', 'realm' => 'Hyjal'],
        ['name' => 'Jaina', 'realm' => 'Archimonde'],
    ]);

    $this->getJson('/api/user/characters')
        ->assertOk()
        ->assertJsonCount(2);
});

test('index returns 500 when service throws exception', function (): void {
    $mock = $this->mock(UserCharacterService::class);
    $mock->shouldReceive('isAuthenticated')->once()->andReturn(true);
    $mock->shouldReceive('getUserCharacters')->once()->andThrow(new \Exception('API timeout'));

    $this->getJson('/api/user/characters')
        ->assertStatus(500)
        ->assertJson(['error' => 'Failed to fetch characters']);
});

test('class icons returns 500 when service throws exception', function (): void {
    $mock = $this->mock(UserCharacterService::class);
    $mock->shouldReceive('getClassIcons')->once()->andThrow(new \Exception('Cache error'));

    $this->getJson('/api/class-icons')
        ->assertStatus(500);
});
