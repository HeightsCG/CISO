<?php
class ClientsController extends Controller {

    public $protected = 1;
    public $json_actions = array(
        'load_evidenceAction',
        'search_evidenceAction',
        'upload_evidenceAction',
        'save_evidenceAction',
        'delete_evidenceAction',
        'load_evidence_linksAction',
        'evidence_urlAction'
    );
    public $clients_model;
    public $evidence_model;

    // Evidence is whatever the assessor was handed: policies, screenshots, config
    // dumps, signed contracts. Anything executable is refused outright.
    const MAX_UPLOAD_BYTES = 26214400;
    const ALLOWED_EXTENSIONS = array(
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf', 'odt', 'ods',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'heic',
        'zip', 'gz', 'tgz', '7z', 'json', 'xml', 'log', 'msg', 'eml'
    );

    public function __construct(){
        parent::__construct();
        $this->clients_model = new ClientsModel();
        $this->evidence_model = new EvidenceModel();
        $this->enforce_json_session();
    }

    /**
     * This controller serves pages and JSON from the same class, so the base $json
     * flag cannot be used: it would answer /clients itself with a 401 body instead
     * of letting layout.php render the login form. Gated once here for every
     * endpoint rather than repeated in each action.
     */
    private function enforce_json_session(): void
    {
        if (!in_array(Main::method_name(), $this->json_actions, true)) {
            return;
        }

        if (!empty(Session::get('user_id'))) {
            return;
        }

        http_response_code(401);
        echo json_encode(array(
            'success' => false,
            'message' => 'Your session has expired. Sign in again.'
        ));
        exit;
    }

    public function indexAction(){
        $this->view->render();
    }

    public function detailAction(){

        $client = $this->clients_model->get_client(Main::get_param('id'), Session::get('company_id'));

        if (!is_array($client) || count($client) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->client = $client[0];
        $this->view->render();
    }

    public function formAction(){

        $client_id = Main::get_param('id');

        if (empty($client_id)) {
            $this->view->client = null;
            $this->view->render();
            return;
        }

        $client = $this->clients_model->get_client($client_id, Session::get('company_id'));

        if (!is_array($client) || count($client) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->client = $client[0];
        $this->view->render();
    }

    public function evidenceAction(){

        $client = $this->clients_model->get_client(Main::get_param('id'), Session::get('company_id'));

        if (!is_array($client) || count($client) !== 1) {
            Errors::page_not_found();
            return;
        }

        $this->view->client = $client[0];
        $this->view->render();
    }

    public function load_evidenceAction(){

        $client_id = (int) ($this->post['client_id'] ?? 0);

        if (!$this->owns_client($client_id)) {
            echo json_encode(array());
            exit;
        }

        echo json_encode($this->evidence_model->load_evidence($client_id, Session::get('company_id')));
    }

    /** Paged, filtered vault lookup - the picker never pulls the whole vault. */
    public function search_evidenceAction(){

        $client_id = (int) ($this->post['client_id'] ?? 0);

        if (!$this->owns_client($client_id)) {
            echo json_encode(array('total' => 0, 'rows' => array()));
            exit;
        }

        $term = $this->input('search');
        $limit = (int) ($this->post['limit'] ?? 50);
        $offset = (int) ($this->post['offset'] ?? 0);
        $sort = $this->input('sort');
        $dir = $this->input('dir');

        // The total only changes when the search term does, so it is counted on
        // the first page and carried by the client for the rest. Re-counting on
        // every scroll page doubled the work for a number that never moved.
        $response = array(
            'rows' => $this->evidence_model->search_evidence($client_id, Session::get('company_id'), $term, $limit, $offset, $sort, $dir)
        );

        if ($offset === 0) {
            $response['total'] = $this->evidence_model->count_evidence($client_id, Session::get('company_id'), $term);
        }

        echo json_encode($response);
    }

    public function load_evidence_linksAction(){

        $evidence_id = (int) ($this->post['evidence_id'] ?? 0);
        $evidence = $this->evidence_model->get_evidence($evidence_id, Session::get('company_id'));

        if (count($evidence) !== 1) {
            echo json_encode(array());
            exit;
        }

        echo json_encode($this->evidence_model->load_evidence_links($evidence_id, Session::get('company_id')));
    }

    public function upload_evidenceAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $client_id = (int) ($this->post['client_id'] ?? 0);
        $evidence_title = $this->input('evidence_title');
        $description = $this->input('description');

        if (!$this->owns_client($client_id)) {
            $response['message'] = 'That client could not be found';
            echo json_encode($response);
            exit;
        }

        if ($evidence_title === '') {
            $response['message'] = 'Evidence title is required';
            echo json_encode($response);
            exit;
        }

        if (!isset($_FILES['evidence_file']) || $_FILES['evidence_file']['error'] !== UPLOAD_ERR_OK) {
            $response['message'] = 'Choose a file to upload';
            echo json_encode($response);
            exit;
        }

        $tmp_name = $_FILES['evidence_file']['tmp_name'];

        if (!is_uploaded_file($tmp_name)) {
            $response['message'] = 'That upload could not be read';
            echo json_encode($response);
            exit;
        }

        if ($_FILES['evidence_file']['size'] > self::MAX_UPLOAD_BYTES) {
            $response['message'] = 'The file must be 25MB or smaller';
            echo json_encode($response);
            exit;
        }

        $original_name = basename((string) $_FILES['evidence_file']['name']);
        $extension = strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION));

        if ($extension === '' || !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $response['message'] = 'That file type is not accepted as evidence';
            echo json_encode($response);
            exit;
        }

        if (!S3Service::configured()) {
            $response['message'] = 'File storage is not configured';
            echo json_encode($response);
            exit;
        }

        $content_type = 'application/octet-stream';

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected = finfo_file($finfo, $tmp_name);
            finfo_close($finfo);
            if (is_string($detected) && $detected !== '') {
                $content_type = $detected;
            }
        }

        // Private, and keyed by company then client so a stray key can never be
        // read across orgs even if the bucket policy is loosened later.
        $key = 'evidence/'.Session::get('company_id').'/'.$client_id.'/'
            .bin2hex(random_bytes(12)).'.'.$extension;

        if (!S3Service::put_private($key, $tmp_name, $content_type)) {
            $response['message'] = 'That file could not be stored';
            echo json_encode($response);
            exit;
        }

        $evidence_id = $this->evidence_model->add_evidence(
            Session::get('company_id'),
            $client_id,
            $evidence_title,
            $description,
            $key,
            $original_name,
            (int) $_FILES['evidence_file']['size'],
            $content_type,
            Session::get('user_id')
        );

        $response['success'] = true;
        $response['message'] = 'Evidence uploaded';
        $response['evidence_id'] = (int) $evidence_id;
        echo json_encode($response);
    }

    public function save_evidenceAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $evidence_id = (int) ($this->post['evidence_id'] ?? 0);
        $evidence_title = $this->input('evidence_title');
        $description = $this->input('description');

        $evidence = $this->evidence_model->get_evidence($evidence_id, Session::get('company_id'));

        if (count($evidence) !== 1) {
            $response['message'] = 'That evidence could not be found';
            echo json_encode($response);
            exit;
        }

        if ($evidence_title === '') {
            $response['message'] = 'Evidence title is required';
            echo json_encode($response);
            exit;
        }

        $this->evidence_model->update_evidence(
            $evidence_id,
            Session::get('company_id'),
            $evidence_title,
            $description,
            Session::get('user_id')
        );

        $response['success'] = true;
        $response['message'] = 'Evidence updated';
        echo json_encode($response);
    }

    public function delete_evidenceAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $evidence_id = (int) ($this->post['evidence_id'] ?? 0);
        $evidence = $this->evidence_model->get_evidence($evidence_id, Session::get('company_id'));

        if (count($evidence) !== 1) {
            $response['message'] = 'That evidence could not be found';
            echo json_encode($response);
            exit;
        }

        $links = $this->evidence_model->load_evidence_links($evidence_id, Session::get('company_id'));

        $this->evidence_model->delete_evidence($evidence_id, Session::get('company_id'), Session::get('user_id'));

        if ($evidence[0]['file_key'] !== '') {
            S3Service::delete_key($evidence[0]['file_key']);
        }

        $response['success'] = true;
        $response['message'] = 'Evidence deleted'
            .(count($links) > 0 ? ', '.count($links).' '.(count($links) === 1 ? 'attachment' : 'attachments').' removed' : '');
        $response['unlinked'] = count($links);
        echo json_encode($response);
    }

    public function evidence_urlAction(){

        $response = array(
            'success' => false,
            'message' => 'Something went wrong'
        );

        $evidence_id = (int) ($this->post['evidence_id'] ?? 0);
        $evidence = $this->evidence_model->get_evidence($evidence_id, Session::get('company_id'));

        if (count($evidence) !== 1) {
            $response['message'] = 'That evidence could not be found';
            echo json_encode($response);
            exit;
        }

        // Short-lived signed URL: the object itself stays private, so a link that
        // leaks stops working within minutes.
        $url = S3Service::presigned_get_url($evidence[0]['file_key'], 300);

        if ($url === '') {
            $response['message'] = 'That file could not be opened';
            echo json_encode($response);
            exit;
        }

        $response['success'] = true;
        $response['message'] = 'ok';
        $response['url'] = $url;
        echo json_encode($response);
    }

    private function owns_client($client_id): bool
    {
        if (empty($client_id)) {
            return false;
        }

        $client = $this->clients_model->get_client($client_id, Session::get('company_id'));

        return is_array($client) && count($client) === 1;
    }

    /**
     * clean_post_data() HTML-encodes every field on the way in; decoding here keeps
     * one encoding in the table and leaves escaping to the views.
     */
    private function input(string $key): string
    {
        if (!isset($this->post[$key])) {
            return '';
        }

        return trim(html_entity_decode((string) $this->post[$key], ENT_QUOTES, 'UTF-8'));
    }

}
