<?php
class ControllerExtensionPaymentPayoutsPay extends Controller {
    private $error = array();

    public function index() {
        $this->language->load('extension/payment/payouts_pay');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payouts_pay', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('extension/extension', 'user_token=' . $this->session->data['user_token'], true));
        }

        $data['heading_title'] = $this->language->get('heading_title');

        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['text_all_zones'] = $this->language->get('text_all_zones');
        $data['text_yes'] = $this->language->get('text_yes');
        $data['text_no'] = $this->language->get('text_no');
        $data['text_authorization'] = $this->language->get('text_authorization');
        $data['text_sale'] = $this->language->get('text_sale');

        $data['entry_client_id'] = $this->language->get('entry_client_id');
        $data['entry_secret'] = $this->language->get('entry_secret');
        $data['entry_sandbox'] = $this->language->get('entry_sandbox');
        $data['entry_transaction'] = $this->language->get('entry_transaction');
        $data['entry_total'] = $this->language->get('entry_total');
        $data['entry_order_status'] = $this->language->get('entry_order_status');
        $data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_sort_order'] = $this->language->get('entry_sort_order');

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['client_id'])) {
            $data['error_client_id'] = $this->error['client_id'];
        } else {
            $data['error_client_id'] = '';
        }

        if (isset($this->error['secret'])) {
            $data['error_secret'] = $this->error['secret'];
        } else {
            $data['error_secret'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('extension/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/payouts_pay', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/payment/payouts_pay', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('extension/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        if (isset($this->request->post['payouts_pay_client_id'])) {
            $data['payouts_pay_client_id'] = $this->request->post['payouts_pay_client_id'];
        } else {
            $data['payouts_pay_client_id'] = $this->config->get('payouts_pay_client_id');
        }

        if (isset($this->request->post['payouts_pay_secret'])) {
            $data['payouts_pay_secret'] = $this->request->post['payouts_pay_secret'];
        } else {
            $data['payouts_pay_secret'] = $this->config->get('payouts_pay_secret');
        }

        if (isset($this->request->post['payouts_pay_sandbox'])) {
            $data['payouts_pay_sandbox'] = $this->request->post['payouts_pay_sandbox'];
        } else {
            $data['payouts_pay_sandbox'] = $this->config->get('payouts_pay_sandbox');
        }

        if (isset($this->request->post['payouts_pay_transaction'])) {
            $data['payouts_pay_transaction'] = $this->request->post['payouts_pay_transaction'];
        } else {
            $data['payouts_pay_transaction'] = $this->config->get('payouts_pay_transaction');
        }

        if (isset($this->request->post['payouts_pay_total'])) {
            $data['payouts_pay_total'] = $this->request->post['payouts_pay_total'];
        } else {
            $data['payouts_pay_total'] = $this->config->get('payouts_pay_total');
        }

        if (isset($this->request->post['payouts_pay_order_status_id'])) {
            $data['payouts_pay_order_status_id'] = $this->request->post['payouts_pay_order_status_id'];
        } else {
            $data['payouts_pay_order_status_id'] = $this->config->get('payouts_pay_order_status_id');
        }

        if (isset($this->request->post['payouts_pay_geo_zone_id'])) {
            $data['payouts_pay_geo_zone_id'] = $this->request->post['payouts_pay_geo_zone_id'];
        } else {
            $data['payouts_pay_geo_zone_id'] = $this->config->get('payouts_pay_geo_zone_id');
        }

        if (isset($this->request->post['payouts_pay_status'])) {
            $data['payouts_pay_status'] = $this->request->post['payouts_pay_status'];
        } else {
            $data['payouts_pay_status'] = $this->config->get('payouts_pay_status');
        }

        if (isset($this->request->post['payouts_pay_sort_order'])) {
            $data['payouts_pay_sort_order'] = $this->request->post['payouts_pay_sort_order'];
        } else {
            $data['payouts_pay_sort_order'] = $this->config->get('payouts_pay_sort_order');
        }

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/payouts_pay', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/payment/payouts_pay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['payouts_pay_client_id']) {
            $this->error['client_id'] = $this->language->get('error_client_id');
        }

        if (!$this->request->post['payouts_pay_secret']) {
            $this->error['secret'] = $this->language->get('error_secret');
        }

        if (!$this->error) {
            return true;
        } else {
            return false;
        }
    }
}
?>
