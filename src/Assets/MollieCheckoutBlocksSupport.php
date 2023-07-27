<?php

namespace Mollie\WooCommerce\Assets;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\Gateway\MolliePaymentGatewayI;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\Shared\Data;

final class MollieCheckoutBlocksSupport extends AbstractPaymentMethodType
{
    protected $name = "mollie";
    /** @var string $scriptHandle */
    protected $scriptHandle = "mollie_block_index";

    /** @var Data */
    protected $dataService;
    /** @var array */
    protected $gatewayInstances;
    /** @var string $registerScriptUrl */
    protected $registerScriptUrl;
    /** @var string $registerScriptVersion */
    protected $registerScriptVersion;
    public function __construct(Data $dataService, array $gatewayInstances, string $registerScriptUrl, string $registerScriptVersion)
    {
        $this->dataService = $dataService;
        $this->gatewayInstances = $gatewayInstances;
        $this->registerScriptUrl = $registerScriptUrl;
        $this->registerScriptVersion = $registerScriptVersion;
    }

    public function initialize()
    {
        //
    }

    public function get_payment_method_script_handles()
    {
        wp_register_script(
            $this->scriptHandle,
            $this->registerScriptUrl,
            ['wc-blocks-registry', 'underscore', 'jquery'],
            $this->registerScriptVersion,
            true
        );

        wp_localize_script(
            $this->scriptHandle,
            'mollieBlockData',
            [
                'gatewayData' => $this->gatewayDataForWCBlocks($this->dataService, $this->gatewayInstances),
            ]
        );
        return [$this->scriptHandle];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => "Credit card",
            'description' => "Credit card description",
            'supports' => $this->get_supported_features(),
        ];
    }

    private function gatewayDataForWCBlocks(Data $dataService, array $gatewayInstances): array
    {
        $filters = $dataService->wooCommerceFiltersForCheckout();
        $availableGateways = WC()->payment_gateways()->get_available_payment_gateways();
        $availablePaymentMethods = [];
        /**
         * @var MolliePaymentGatewayI $gateway
         * psalm-suppress  UnusedForeachValue
         */
        foreach ($availableGateways as $key => $gateway) {
            if (strpos($key, 'mollie_wc_gateway_') === false) {
                unset($availableGateways[$key]);
            }
        }
        if (
            isset($filters['amount']['currency'])
            && isset($filters['locale'])
            && isset($filters['billingCountry'])
        ) {
            $filterKey = "{$filters['amount']['currency']}-{$filters['locale']}-{$filters['billingCountry']}";
            foreach ($availableGateways as $key => $gateway) {
                $availablePaymentMethods[$filterKey][$key] = $gateway->paymentMethod()->getProperty('id');
            }
        }

        $dataToScript = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'filters' => [
                'currency' => isset($filters['amount']['currency']) ? $filters['amount']['currency'] : false,
                'cartTotal' => isset($filters['amount']['value']) ? $filters['amount']['value'] : false,
                'paymentLocale' => isset($filters['locale']) ? $filters['locale'] : false,
                'billingCountry' => isset($filters['billingCountry']) ? $filters['billingCountry'] : false,
            ],
        ];
        $gatewayData = [];
        $isSepaEnabled = isset($gatewayInstances['mollie_wc_gateway_directdebit']) && $gatewayInstances['mollie_wc_gateway_directdebit']->enabled === 'yes';
        /** @var MolliePaymentGateway $gateway */
        foreach ($gatewayInstances as $gatewayKey => $gateway) {
            /** @var string $gatewayId */
            $gatewayId = is_string($gateway->paymentMethod()->getProperty('id')) ? $gateway->paymentMethod(
            )->getProperty('id') : "";

            if ($gateway->enabled !== 'yes' || $gatewayId === 'directdebit') {
                continue;
            }
            $content = $gateway->paymentMethod()->getProcessedDescriptionForBlock();
            $issuers = false;
            if ($gateway->paymentMethod()->getProperty('paymentFields') === true) {
                $paymentFieldsService = $gateway->paymentMethod()->paymentFieldsService();
                $paymentFieldsService->setStrategy($gateway->paymentMethod());
                $issuers = $gateway->paymentMethod()->paymentFieldsService()->getStrategyMarkup($gateway);
            }
            if ($gatewayId === 'creditcard') {
                $content .= $issuers;
                $issuers = false;
            }
            $title = $gateway->paymentMethod()->title();
            $labelMarkup = "<span style='margin-right: 1em'>{$title}</span>{$gateway->icon}";
            $hasSurcharge = $gateway->paymentMethod()->hasSurcharge();
            $gatewayData[] = [
                'name' => $gatewayKey,
                'label' => $labelMarkup,
                'content' => $content,
                'issuers' => $issuers,
                'hasSurcharge' => $hasSurcharge,
                'title' => $title,
                'contentFallback' => __(
                    'Please choose a billing country to see the available payment methods',
                    'mollie-payments-for-woocommerce'
                ),
                'edit' => $content,
                'paymentMethodId' => $gatewayKey,
                'allowedCountries' => is_array(
                    $gateway->paymentMethod()->getProperty('allowed_countries')
                ) ? $gateway->paymentMethod()->getProperty('allowed_countries') : [],
                'ariaLabel' => $gateway->paymentMethod()->getProperty('defaultDescription'),
                'supports' => $this->gatewaySupportsFeatures($gateway->paymentMethod(), $isSepaEnabled),
                'errorMessage' => $gateway->paymentMethod()->getProperty('errorMessage'),
                'companyPlaceholder' => $gateway->paymentMethod()->getProperty('companyPlaceholder'),
                'phonePlaceholder' => $gateway->paymentMethod()->getProperty('phonePlaceholder'),
                'birthdatePlaceholder' => $gateway->paymentMethod()->getProperty('birthdatePlaceholder'),
            ];
        }
        $dataToScript['gatewayData'] = $gatewayData;
        $dataToScript['availableGateways'] = $availablePaymentMethods;

        return $dataToScript;
    }

    public function gatewaySupportsFeatures(PaymentMethodI $paymentMethod, bool $isSepaEnabled): array
    {
        $supports = (array)$paymentMethod->getProperty('supports');
        $isSepaPaymentMethod = (bool)$paymentMethod->getProperty('SEPA');
        if ($isSepaEnabled && $isSepaPaymentMethod) {
            array_push($supports, 'subscriptions');
        }

        return $supports;
    }
}
