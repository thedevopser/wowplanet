<?php

declare(strict_types=1);

use App\Infrastructure\Logging\AdminAudit;

test('an administrator action is written to the audit trail with who did it', function (): void {
    resolve(AdminAudit::class)->record('Import steered from the admin panel', 'admin-1', ['jobId' => 'job-1']);

    expect(auditTrail())->toBe([
        ['message' => 'Import steered from the admin panel', 'context' => ['jobId' => 'job-1', 'actor' => 'admin-1']],
    ]);
});

test('the audit trail is kept at the information level whatever the application log level', function (): void {
    // Lu dans le fichier : la suite remplace ce canal par un journal en mémoire.
    $channel = (require config_path('logging.php'))['channels']['audit'];

    expect($channel['level'])->toBe('info')
        ->and($channel['path'])->toBe(storage_path('logs/audit.log'));
});

test('an audit trail that cannot be written never undoes the action, and the failure shows among the recent errors', function (): void {
    $unwritable = sys_get_temp_dir().'/audit-'.uniqid();
    mkdir($unwritable);
    config(['logging.channels.audit' => ['driver' => 'single', 'path' => $unwritable, 'level' => 'info']]);

    expect(fn () => resolve(AdminAudit::class)->record('Import steered from the admin panel', 'admin-1', ['jobId' => 'job-1']))
        ->not->toThrow(Throwable::class);

    expect(App\Models\ApplicationError::query()->sole()->message)->toBe('Admin audit trail could not be written');

    rmdir($unwritable);
});
