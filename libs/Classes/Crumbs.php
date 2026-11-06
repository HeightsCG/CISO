<?php

/**
 * The breadcrumb trail shown in the top bar. Worked out from the page being
 * rendered and the records its controller handed to the view, so controllers
 * need no breadcrumb code of their own.
 */
class Crumbs
{
    public static function html($view): string
    {
        $trail = self::trail($view);

        if (count($trail) === 0) {
            return '';
        }

        $html = '<nav class="crumbs" aria-label="Breadcrumb"><ol class="crumbs__list">';
        $last = count($trail) - 1;

        foreach ($trail as $i => $crumb) {

            $label = htmlspecialchars(html_entity_decode((string) $crumb[0], ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');

            if ($i === $last || $crumb[1] === '') {
                $html .= '<li class="crumbs__item"><span'.($i === $last ? ' aria-current="page"' : '').'>'.$label.'</span></li>';
                continue;
            }

            $html .= '<li class="crumbs__item"><a href="'.htmlspecialchars($crumb[1], ENT_QUOTES, 'UTF-8').'">'.$label.'</a></li>';
        }

        return $html.'</ol></nav>';
    }

    private static function trail($view): array
    {
        $controller = (string) ($view->controller ?? '');
        $method = (string) ($view->method ?? '');
        $page = $controller.'/'.($method === '' ? 'index' : $method);

        $assessment = self::record($view, 'assessment');
        $client = self::record($view, 'client');
        $invoice = self::record($view, 'invoice');
        $subscription = self::record($view, 'subscription');
        $standard = self::record($view, 'standard');

        switch ($page) {

            case 'index/index':
                return array(
                    array('Dashboard', '')
                );

            case 'clients/index':
                return array(
                    array('Clients', '')
                );

            case 'clients/detail':
                return array(
                    array('Clients', '/clients'),
                    array($client['company_name'] ?? 'Client', '')
                );

            case 'clients/add':
                return array(
                    array('Clients', '/clients'),
                    array('Add Client', '')
                );

            case 'assessments/index':
                return array(
                    array('Assessments', '')
                );

            case 'assessments/detail':
                return array(
                    array('Assessments', '/assessments'),
                    array($assessment['assessment_name'] ?? 'Assessment', '')
                );

            case 'billing/invoice':
            case 'billing/form':
                return self::billing(
                    $invoice['client_id'] ?? ($view->client_id ?? 0),
                    $invoice['client_name'] ?? '',
                    ($invoice === null ? 'New Invoice' : (($invoice['invoice_number'] ?? '') === '' ? 'Invoice' : $invoice['invoice_number']))
                );

            case 'billing/subscription':
            case 'billing/subscriptionform':
                return self::billing(
                    $subscription['client_id'] ?? ($view->client_id ?? 0),
                    $subscription['client_name'] ?? '',
                    ($subscription === null ? 'New Subscription' : $subscription['subscription_name'])
                );

            case 'standards/index':
                return array(
                    array('Standards', '')
                );

            case 'standards/detail':
                return array(
                    array('Standards', '/standards'),
                    array($standard['standard_name'] ?? 'Standard', '')
                );

            case 'standards/form':
                return ($standard === null
                    ? array(
                        array('Standards', '/standards'),
                        array('Add Standard', '')
                    )
                    : array(
                        array('Standards', '/standards'),
                        array($standard['standard_name'], '/standards/detail/id/'.((int) $standard['id'])),
                        array('Edit', '')
                    ));

            case 'settings/index':
                return array(
                    array('Settings', '')
                );

            case 'settings/users':
                return array(
                    array('Settings', '/settings'),
                    array('Users', '')
                );

            case 'users/index':
                return array(
                    array('User Accounts', '')
                );

            case 'profile/index':
                return array(
                    array('My Profile', '')
                );

            case 'subscription/index':
                return array(
                    array('Subscription', '')
                );

            case 'portal/index':
                return array(
                    array('Your Assessments', '')
                );

            case 'portal/invoices':
                return array(
                    array('Invoices', '')
                );

            case 'portal/assessment':
                return array(
                    array('Your Assessments', '/portal'),
                    array($assessment['assessment_name'] ?? 'Assessment', '')
                );
        }

        return array();
    }

    private static function billing($client_id, $client_name, $label): array
    {
        $trail = array(
            array('Clients', '/clients')
        );

        if ((int) $client_id > 0) {
            $trail[] = array(($client_name === '' ? 'Client' : $client_name), '/clients/detail/id/'.((int) $client_id));
        }

        $trail[] = array($label, '');

        return $trail;
    }

    private static function record($view, string $key): ?array
    {
        $value = $view->$key ?? null;

        return (is_array($value) ? $value : null);
    }
}
