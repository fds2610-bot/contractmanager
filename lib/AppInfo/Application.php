<?php

declare(strict_types=1);

namespace OCA\NcVertragswerk\AppInfo;

use OCA\NcVertragswerk\Listener\UserDeletedListener;
use OCA\NcVertragswerk\Notification\Notifier;
use OCA\NcVertragswerk\Search\ContractSearchProvider;
use OCA\NcVertragswerk\UserMigration\ContractMigrator;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {
    public const APP_ID = 'nc_vertragswerk';

    public function __construct() {
        parent::__construct(self::APP_ID);

        // Load composer autoloader for third-party dependencies (smalot/pdfparser)
        $autoloader = __DIR__ . '/../../vendor/autoload.php';
        if (file_exists($autoloader)) {
            require_once $autoloader;
        }
    }

    public function register(IRegistrationContext $context): void {
        $context->registerSearchProvider(ContractSearchProvider::class);
        $context->registerUserMigrator(ContractMigrator::class);
        $context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
        $context->registerNotifierService(Notifier::class);
    }

    public function boot(IBootContext $context): void {
        // Boot logic
    }
}
