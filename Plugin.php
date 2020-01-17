<?php namespace Sarahman\Mailer;

use Sarahman\Mailer\Transport\SendgridTransport;
use System\Classes\PluginBase;
use System\Models\MailSetting;

class Plugin extends PluginBase
{
    const MODE_SENDGRID = 'sendgrid';

    /**
     * @var boolean Determine if this plugin should have elevated privileges.
     */
    public $elevated = true;

    public function pluginDetails()
    {
        return [
            'name'        => 'SendGrid Mailer Driver',
            'description' => 'This plugin is used in October CMS for email sending functionality through Sendgrid driver.',
            'author'      => 'Syed Abidur Rahman',
            'icon'        => 'icon-user'
        ];
    }

    public function boot()
    {
        \Event::listen('backend.form.extendFields', function($widget) {

            // Only for the Settings controller
            if (!$widget->getController() instanceof \System\Controllers\Settings) {
                return;
            }

            // Only for the MailSetting model
            if (!$widget->model instanceof MailSetting) {
                return;
            }

            $field = $widget->getField('send_mode');
            $field->options(array_merge($field->options(), [self::MODE_SENDGRID => trans('sarahman.mailer::lang.mail.sendgrid')]));

            // Add an extra birthday field
            $widget->addFields([
                'sendgrid_api_key' => [
                    'label' => trans('sarahman.mailer::lang.mail.sendgrid_api_key'),
                    'comment' => trans('sarahman.mailer::lang.mail.sendgrid_api_key_comment'),
                    'tab' => ' ',
                    'trigger' => [
                        'action' => 'show',
                        'field' => 'send_mode',
                        'condition' => 'value[sendgrid]'
                    ]
                ],
            ], 'secondary');
        });

        \App::extend('swift.transport', function(\Illuminate\Mail\TransportManager $manager) {
            return $manager->extend(self::MODE_SENDGRID, function() {
                $config = \App::make('config');
                return new SendgridTransport($config->get('services.sendgrid.api_key'));
            });
        });

        MailSetting::extend(function($model) {
            $model->bindEvent('model.beforeValidate', function () use ($model) {
                $model->rules['sendgrid_api_key'] = 'required_if:send_mode,' . self::MODE_SENDGRID;
            });
        });
    }

    public function register()
    {
        /*
         * Override system mailer with mail settings
         */
        \Event::listen('mailer.register', function () {
            $settings = MailSetting::instance();
            if ($settings->send_mode === self::MODE_SENDGRID) {
                $config = \App::make('config');
                $config->set('services.sendgrid.api_key', $settings->sendgrid_api_key);
            }
        });
    }
}
