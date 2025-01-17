<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Ideal extends AbstractPaymentMethod implements PaymentMethodI
{
    protected const DEFAULT_ISSUERS_DROPDOWN = 'yes';

    public function getConfig(): array
    {
        return [
            'id' => 'ideal',
            'defaultTitle' => __('iDEAL', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => '',
            'defaultDescription' => __('Select your bank', 'mollie-payments-for-woocommerce'),
            'paymentFields' => true,
            'instructions' => true,
            'supports' => [
                'products',
                'refunds',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => true,
            'SEPA' => true,
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        $searchKey = 'advanced';
        $keys = array_keys($generalFormFields);
        $index = array_search($searchKey, $keys);
        $before = array_slice($generalFormFields, 0, $index + 1, true);
        $after = array_slice($generalFormFields, $index + 1, null, true);
        $paymentMethodFormFieds =  [
            'issuers_dropdown_shown' => [
                'title' => __('Show iDEAL banks dropdown', 'mollie-payments-for-woocommerce'),
                'type' => 'checkbox',
                'description' => sprintf(
                    __(
                        'If you disable this, a dropdown with various iDEAL banks will not be shown in the WooCommerce checkout, so users will select a iDEAL bank on the Mollie payment page after checkout.',
                        'mollie-payments-for-woocommerce'
                    ),
                    $this->getConfig()['defaultTitle']
                ),
                'default' => self::DEFAULT_ISSUERS_DROPDOWN,
            ],
            'issuers_empty_option' => [
                'title' => __('Issuers empty option', 'mollie-payments-for-woocommerce'),
                'type' => 'text',
                'description' => sprintf(
                    __(
                        "This text will be displayed as the first option in the iDEAL issuers drop down, if nothing is entered, 'Select your bank' will be shown. Only if the above 'Show iDEAL banks dropdown' is enabled.",
                        'mollie-payments-for-woocommerce'
                    ),
                    $this->getConfig()['defaultTitle']
                ),
                'default' => __('Select your bank', 'mollie-payments-for-woocommerce'),
            ],
        ];
        $before = array_merge($before, $paymentMethodFormFieds);
        $formFields = array_merge($before, $after);
        return $formFields;
    }
}
