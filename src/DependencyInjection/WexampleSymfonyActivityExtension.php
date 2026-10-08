<?php

namespace Wexample\SymfonyActivity\DependencyInjection;

use DH\AuditorBundle\DHAuditorBundle;
use LogicException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyActivity\Service\ActivityAuditUserProvider;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;

class WexampleSymfonyActivityExtension extends AbstractWexampleSymfonyExtension
{
    /**
     * auditor-bundle records the field changes of the entities an application
     * marks `#[Auditable]`: configured here, so that marking them is all there
     * is to do. An application's own `dh_auditor` configuration still wins.
     */
    public function prepend(ContainerBuilder $container): void
    {
        parent::prepend($container);

        if (! $container->hasExtension('dh_auditor')) {
            throw new LogicException(sprintf('symfony-activity records field changes through auditor-bundle: register %s in config/bundles.php.', DHAuditorBundle::class));
        }

        $container->prependExtensionConfig('dh_auditor', [
            // The time the activity table is written in.
            'timezone' => 'UTC',
            // Who changed a field, named as symfony-activity names an actor.
            'user_provider' => ActivityAuditUserProvider::class,
            'providers' => [
                'doctrine' => [
                    // auditor's viewer is left off, its default: symfony-activity-ds
                    // reads the changes along with the events.
                    'table_suffix' => '_audit',
                ],
            ],
        ]);
    }

    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('wexample_symfony_activity.categories', $config['categories']);
        $container->setParameter('wexample_symfony_activity.client_categories', $config['client_categories']);

        $this->loadConfig(
            __DIR__,
            $container
        );
    }
}
