<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Mail\CheckoutAssetMail;
use App\Mail\CheckoutAccessoryMail;
use App\Mail\CheckoutConsumableMail;
use App\Mail\CheckoutLicenseMail;
use App\Models\Accessory;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Maintenance;
use App\Models\CheckoutAcceptance;
use App\Models\Company;
use App\Models\CustomField;
use App\Models\Depreciation;
use App\Models\License;
use App\Models\ReportTemplate;
use App\Models\Setting;
use App\Models\Consumable;
use App\Models\Component;
use App\Models\LicenseSeat;
use App\Models\Checkoutable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\View\View;
use League\Csv\Reader;
use Symfony\Component\HttpFoundation\StreamedResponse;
use League\Csv\EscapeFormula;
use App\Http\Requests\CustomAssetReportRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\RedirectResponse;
use TCPDF;
use ZipArchive;
use Exception;
use Illuminate\Validation\ValidationException;

/**
 * This controller handles all actions related to Reports for
 * the Snipe-IT Asset Management application.
 *
 * @version    v1.0
 */
class ReportsController extends Controller
{
    /**
     * Checks for correct permissions
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
    * Returns a view that displays the accessories report.
    *
    * @author [A. Gianotto] [<snipe@snipe.net>]
    * @since [v1.0]
    * @return \Illuminate\Contracts\View\View
    */
    public function getAccessoryReport() : View
    {
        $this->authorize('reports.view');

        return view('reports/accessories');
    }

    /**
    * Exports the accessories to CSV
    *
    * @deprecated Server-side exports have been replaced by datatables export since v2.
    * @author [A. Gianotto] [<snipe@snipe.net>]
    * @see ManufacturersController::getDatatable() method that generates the JSON response
    * @since [v1.0]
    * @return \Illuminate\Http\Response
    */
    public function exportAccessoryReport() : Response
    {
        $this->authorize('reports.view');
        $accessories = Accessory::orderBy('created_at', 'DESC')->get();

        $rows = [];
        $header = [
            trans('admin/accessories/table.title'),
            trans('admin/accessories/general.accessory_category'),
            trans('admin/accessories/general.total'),
            trans('admin/accessories/general.remaining'),
        ];
        $header = array_map('trim', $header);
        $rows[] = implode(', ', $header);

        // Row per accessory
        foreach ($accessories as $accessory) {
            $row = [];
            $row[] = e($accessory->accessory_name);
            $row[] = e($accessory->accessory_category);
            $row[] = e($accessory->total);
            $row[] = e($accessory->remaining);

            $rows[] = implode(',', $row);
        }

        $csv = implode("\n", $rows);
        $response = response()->make($csv, 200);
        $response->header('Content-Type', 'text/csv');
        $response->header('Content-disposition', 'attachment;filename=report.csv');

        return $response;
    }

    /**
    * Show depreciation report for assets.
    *
    * @author [A. Gianotto] [<snipe@snipe.net>]
    * @since [v1.0]
    */
    public function getDeprecationReport() : View
    {
        $this->authorize('reports.view');
        $depreciations = Depreciation::get();
        return view('reports/depreciation')->with('depreciations',$depreciations);
    }

    /**
    * Exports the depreciations to CSV
    *
    * @deprecated Server-side exports have been replaced by datatables export since v2.
    * @author [A. Gianotto] [<snipe@snipe.net>]
    * @since [v1.0]
    */
    public function exportDeprecationReport() : Response
    {
        $this->authorize('reports.view');
        // Grab all the assets
        $assets = Asset::with('model', 'assignedTo', 'assetstatus', 'defaultLoc', 'assetlog')
                       ->orderBy('created_at', 'DESC')->get();

        $csv = \League\Csv\Writer::createFromFileObject(new \SplTempFileObject());
        $csv->setOutputBOM(Reader::BOM_UTF16_BE);

        $rows = [];

        // Create the header row
        $header = [
            trans('admin/hardware/table.asset_tag'),
            trans('admin/hardware/table.title'),
            trans('admin/hardware/table.serial'),
            trans('admin/hardware/table.checkoutto'),
            trans('admin/hardware/table.location'),
            trans('admin/hardware/table.purchase_date'),
            trans('admin/hardware/table.purchase_cost'),
            trans('admin/hardware/table.book_value'),
            trans('admin/hardware/table.diff'),
        ];

        //we insert the CSV header
        $csv->insertOne($header);

        // Create a row per asset
        foreach ($assets as $asset) {
            $row = [];
            $row[] = e($asset->asset_tag);
            $row[] = e($asset->name);
            $row[] = e($asset->serial);

            if ($target = $asset->assignedTo) {
                $row[] = e($target->present()->name());
            } else {
                $row[] = ''; // Empty string if unassigned
            }

            if (($asset->assigned_to > 0) && ($location = $asset->location)) {
                if ($location->city) {
                    $row[] = e($location->city).', '.e($location->state);
                } elseif ($location->name) {
                    $row[] = e($location->name);
                } else {
                    $row[] = '';
                }
            } else {
                $row[] = '';  // Empty string if location is not set
            }

            if ($asset->location) {
                $currency = e($asset->location->currency);
            } else {
                $currency = e(Setting::getSettings()->default_currency);
            }

            $row[] = Helper::getFormattedDateObject($asset->purchase_date, 'date', false);
            $row[] = $currency.Helper::formatCurrencyOutput($asset->purchase_cost);
            $row[] = $currency.Helper::formatCurrencyOutput($asset->getDepreciatedValue());
            $row[] = $currency.Helper::formatCurrencyOutput(($asset->purchase_cost - $asset->getDepreciatedValue()));
            $csv->insertOne($row);
        }

        $csv->output('depreciation-report-'.date('Y-m-d').'.csv');
        die;
    }


    /**
     * Displays audit report.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     */
    public function audit() : View
    {
        $this->authorize('reports.view');
        return view('reports/audit');
    }


    /**
    * Displays activity report.
    *
    * @author [A. Gianotto] [<snipe@snipe.net>]
    * @since [v1.0]
    */
    public function getActivityReport() : View
    {
        $this->authorize('reports.view');

        return view('reports/activity');
    }

    /**
     * Exports the activity report to CSV
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v5.0.7]
     */
    public function postActivityReport(Request $request) : StreamedResponse
    {
        ini_set('max_execution_time', 12000);
        $this->authorize('reports.view');

        \Debugbar::disable();
        $response = new StreamedResponse(function () {
            Log::debug('Starting streamed response');

            // Open output stream
            $handle = fopen('php://output', 'w');
            stream_set_timeout($handle, 2000);

            $header = [
                trans('general.date'),
                trans('general.created_by'),
                trans('general.action'),
                trans('general.type'),
                trans('general.item'),
                trans('general.license_serial'),
                trans('general.model_name'),
                trans('general.model_no'),
                'To',
                trans('general.notes'),
                trans('admin/settings/general.login_ip'),
                trans('admin/settings/general.login_user_agent'),
                trans('general.action_source'),
                'Changed',

            ];
            $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
            Log::debug('Starting headers: '.$executionTime);
            fputcsv($handle, $header);
            $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
            Log::debug('Added headers: '.$executionTime);

            $actionlogs = Actionlog::with('item', 'user', 'target', 'location', 'adminuser')
                ->orderBy('created_at', 'DESC')
                ->chunk(500, function ($actionlogs) use ($handle) {
                    $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
                Log::debug('Walking results: '.$executionTime);
                $count = 0;

                foreach ($actionlogs as $actionlog) {
                    $count++;
                    $target_name = '';

                    if ($actionlog->target) {
                            if ($actionlog->targetType() == 'user') {
                                $target_name = $actionlog->target->display_name;
                        } else {
                            $target_name = $actionlog->target->getDisplayNameAttribute();
                        }
                    }

                    if($actionlog->item){
                        $item_name = e($actionlog->item->getDisplayNameAttribute());
                    } else {
                        $item_name = '';
                    }

                    $row = [
                        $actionlog->created_at,
                        ($actionlog->adminuser) ? e($actionlog->adminuser->display_name) : '',
                        $actionlog->present()->actionType(),
                        e($actionlog->itemType()),
                        ($actionlog->itemType() == 'user') ? $actionlog->filename : $item_name,
                        ($actionlog->item) ? $actionlog->item->serial : null,
                        (($actionlog->item) && ($actionlog->item->model)) ? htmlspecialchars($actionlog->item->model->name, ENT_NOQUOTES) : null,
                        (($actionlog->item) && ($actionlog->item->model))  ? $actionlog->item->model->model_number : null,
                        $target_name,
                        ($actionlog->note) ? e($actionlog->note) : '',
                        $actionlog->log_meta,
                        $actionlog->remote_ip,
                        $actionlog->user_agent,
                        $actionlog->action_source,
                    ];
                    fputcsv($handle, $row);
                }
            });

            // Close the output stream
            fclose($handle);
            $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
            Log::debug('-- SCRIPT COMPLETED IN '.$executionTime);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="activity-report-'.date('Y-m-d-his').'.csv"',
        ]);


        return $response;
    }


    /**
     * Displays license report
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v1.0]
     */
    public function getLicenseReport() : View
    {
        $this->authorize('reports.view');
        $licenses = License::with('depreciation')->orderBy('created_at', 'DESC')
                           ->with('company')
                           ->get();

        return view('reports/licenses', compact('licenses'));
    }

    /**
    * Exports the licenses to CSV
    *
    * @deprecated Server-side exports have been replaced by datatables export since v2.
    * @author [A. Gianotto] [<snipe@snipe.net>]
    * @since [v1.0]
    */
    public function exportLicenseReport() : Response
    {
        $this->authorize('reports.view');
        $licenses = License::orderBy('created_at', 'DESC')->get();

        $rows = [];
        $header = [
            trans('admin/licenses/table.title'),
            trans('admin/licenses/table.serial'),
            trans('admin/licenses/form.seats'),
            trans('admin/licenses/form.remaining_seats'),
            trans('admin/licenses/form.expiration'),
            trans('general.purchase_date'),
            trans('general.depreciation'),
            trans('general.purchase_cost'),
        ];

        $header = array_map('trim', $header);
        $rows[] = implode(', ', $header);

        // Row per license
        foreach ($licenses as $license) {
            $row = [];
            $row[] = e($license->name);
            $row[] = e($license->serial);
            $row[] = e($license->seats);
            $row[] = $license->remaincount();
            $row[] = $license->expiration_date;
            $row[] = $license->purchase_date;
            $row[] = ($license->depreciation != '') ? '' : e($license->depreciation->name);
            $row[] = '"'.Helper::formatCurrencyOutput($license->purchase_cost).'"';

            $rows[] = implode(',', $row);
        }


        $csv      = implode("\n", $rows);
        $response = response()->make($csv, 200);
        $response->header('Content-Type', 'text/csv');
        $response->header('Content-disposition', 'attachment;filename=report.csv');

        return $response;
    }

    /**
    * Returns a form that allows the user to generate a custom CSV report.
    *
    * @author [A. Gianotto] [<snipe@snipe.net>]
    * @see ReportsController::postCustomReport() method that generates the CSV
    * @since [v1.0]
    */
    public function getCustomReport(Request $request) : View
    {
        $this->authorize('reports.view');
        $customfields = CustomField::get();
        $report_templates = ReportTemplate::orderBy('name')->get();

        // The view needs a template to render correctly, even if it is empty...
        $template = new ReportTemplate;

        // Set the report's input values in the cases we were redirected back
        // with validation errors so the report is populated as expected.
        if ($request->old()) {
            $template->name = $request->old('name');
            $template->options = $request->old();
        }

        return view('reports/custom', [
            'customfields' => $customfields,
            'report_templates' => $report_templates,
            'template' => $template,
        ]);
    }

    /**
     * Exports the custom report to CSV
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @see ReportsController::getCustomReport() method that generates form view
     * @since [v1.0]
     */
    public function postCustom(CustomAssetReportRequest $request) : StreamedResponse
    {
        ini_set('max_execution_time', env('REPORT_TIME_LIMIT', 12000)); //12000 seconds = 200 minutes
        $this->authorize('reports.view');


        \Debugbar::disable();
        $customfields = CustomField::get();
        $response = new StreamedResponse(function () use ($customfields, $request) {
            Log::debug('Starting streamed response');
            Log::debug('CSV escaping is set to: '.config('app.escape_formulas'));

            // Open output stream
            $handle = fopen('php://output', 'w');
            stream_set_timeout($handle, 2000);
            
            if ($request->filled('use_bom')) {
                fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            }

            $header = [];

            if ($request->filled('id')) {
                $header[] = trans('general.id');
            }

            if ($request->filled('company')) {
                $header[] = trans('general.company');
            }

            if ($request->filled('asset_name')) {
                $header[] = trans('admin/hardware/form.name');
            }

            if ($request->filled('asset_tag')) {
                $header[] = trans('admin/hardware/table.asset_tag');
            }

            if ($request->filled('model')) {
                $header[] = trans('admin/hardware/form.model');
                $header[] = trans('general.model_no');
            }

            if ($request->filled('category')) {
                $header[] = trans('general.category');
            }

            if ($request->filled('manufacturer')) {
                $header[] = trans('admin/hardware/form.manufacturer');
            }

            if ($request->filled('serial')) {
                $header[] = trans('admin/hardware/table.serial');
            }
            if ($request->filled('purchase_date')) {
                $header[] = trans('admin/hardware/table.purchase_date');
            }

            if ($request->filled('purchase_cost')) {
                $header[] = trans('admin/hardware/table.purchase_cost');
            }

            if ($request->filled('eol')) {
                $header[] = trans('admin/hardware/table.eol');
            }

            if ($request->filled('warranty')) {
                $header[] = trans('admin/hardware/form.warranty');
                $header[] = trans('admin/hardware/form.warranty_expires');
            }

            if ($request->filled('depreciation')) {
                $header[] = trans('admin/hardware/table.book_value');
                $header[] = trans('admin/hardware/table.diff');
                $header[] = trans('admin/hardware/form.fully_depreciated');
            }

            if ($request->filled('order')) {
                $header[] = trans('admin/hardware/form.order');
            }

            if ($request->filled('supplier')) {
                $header[] = trans('general.supplier');
            }

            if ($request->filled('location')) {
                $header[] = trans('admin/hardware/table.location');
            }
            if ($request->filled('location_address')) {
                $header[] = trans('general.address');
                $header[] = trans('general.address');
                $header[] = trans('general.city');
                $header[] = trans('general.state');
                $header[] = trans('general.country');
                $header[] = trans('general.zip');
            }

            if ($request->filled('rtd_location')) {
                $header[] = trans('admin/hardware/form.default_location');
            }
            
            if ($request->filled('rtd_location_address')) {
                $header[] = trans('general.address');
                $header[] = trans('general.address');
                $header[] = trans('general.city');
                $header[] = trans('general.state');
                $header[] = trans('general.country');
                $header[] = trans('general.zip');
            }

            if ($request->filled('assigned_to')) {
                $header[] = trans('admin/hardware/table.checkoutto');
                $header[] = trans('general.type');
            }

            if ($request->filled('username')) {
                $header[] = 'Username';
            }

            if ($request->filled('employee_num')) {
                $header[] = 'Employee No.';
            }

            if ($request->filled('manager')) {
                $header[] = trans('admin/users/table.manager');
            }

            if ($request->filled('department')) {
                $header[] = trans('general.department');
            }

            if ($request->filled('title')) {
                $header[] = trans('admin/users/table.title');
            }

            if ($request->filled('phone')) {
                $header[] = trans('admin/users/table.phone');
            }

            if ($request->filled('user_address')) {
                $header[] = trans('admin/reports/general.custom_export.user_address');
            }

            if ($request->filled('user_city')) {
                $header[] = trans('admin/reports/general.custom_export.user_city');
            }

            if ($request->filled('user_state')) {
                $header[] = trans('admin/reports/general.custom_export.user_state');
            }

            if ($request->filled('user_country')) {
                $header[] = trans('admin/reports/general.custom_export.user_country');
            }

            if ($request->filled('user_zip')) {
                $header[] = trans('admin/reports/general.custom_export.user_zip');
            }

            if ($request->filled('status')) {
                $header[] = trans('general.status');
            }

            if ($request->filled('checkout_date')) {
                $header[] = trans('admin/hardware/table.checkout_date');
            }

            if ($request->filled('checkin_date')) {
                $header[] = trans('admin/hardware/table.last_checkin_date');
            }

            if ($request->filled('expected_checkin')) {
                $header[] = trans('admin/hardware/form.expected_checkin');
            }

            if ($request->filled('created_at')) {
                $header[] = trans('general.created_at');
            }

            if ($request->filled('updated_at')) {
                $header[] = trans('general.updated_at');
            }

            if ($request->filled('deleted_at')) {
                $header[] = trans('general.deleted');
            }

            if ($request->filled('last_audit_date')) {
                $header[] = trans('general.last_audit');
            }

            if ($request->filled('next_audit_date')) {
                $header[] = trans('general.next_audit_date');
            }

            if ($request->filled('notes')) {
                $header[] = trans('general.notes');
            }

            if ($request->filled('url')) {
                $header[] = trans('general.url');
            }


            foreach ($customfields as $customfield) {
                if ($request->input($customfield->db_column_name()) == '1') {
                    $header[] = $customfield->name;
                }
            }

            $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
            Log::debug('Starting headers: '.$executionTime);
            fputcsv($handle, $header);
            $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
            Log::debug('Added headers: '.$executionTime);

            $assets = Asset::select('assets.*')->with(
                'location', 'assetstatus', 'company', 'defaultLoc', 'assignedTo',
                'model.category', 'model.manufacturer', 'supplier');
            
            if ($request->filled('by_location_id')) {
                $assets->whereIn('assets.location_id', $request->input('by_location_id'));
            }

            if ($request->filled('by_rtd_location_id')) {
                $assets->whereIn('assets.rtd_location_id', $request->input('by_rtd_location_id'));
            }

            if ($request->filled('by_supplier_id')) {
                $assets->whereIn('assets.supplier_id', $request->input('by_supplier_id'));
            }

            if ($request->filled('by_company_id')) {
                $assets->whereIn('assets.company_id', $request->input('by_company_id'));
            }

            if ($request->filled('by_model_id')) {
                $assets->whereIn('assets.model_id', $request->input('by_model_id'));
            }

            if ($request->filled('by_category_id')) {
                $assets->InCategory($request->input('by_category_id'));
            }

            if ($request->filled('by_dept_id')) {
                $assets->CheckedOutToTargetInDepartment($request->input('by_dept_id'));
            }

            if ($request->filled('by_manufacturer_id')) {
                $assets->ByManufacturer($request->input('by_manufacturer_id'));
            }

            if ($request->filled('by_order_number')) {
                $assets->where('assets.order_number', $request->input('by_order_number'));
            }

            if ($request->filled('by_status_id')) {
                $assets->whereIn('assets.status_id', $request->input('by_status_id'));
            }

            if (($request->filled('purchase_start')) && ($request->filled('purchase_end'))) {
                $assets->whereBetween('assets.purchase_date', [$request->input('purchase_start'), $request->input('purchase_end')]);
            }

            if (($request->filled('created_start')) && ($request->filled('created_end'))) {
                $created_start = Carbon::parse($request->input('created_start'))->startOfDay();
                $created_end = Carbon::parse($request->input('created_end'))->endOfDay();

                $assets->whereBetween('assets.created_at', [$created_start, $created_end]);
            }

            if (($request->filled('checkout_date_start')) && ($request->filled('checkout_date_end'))) {
                $checkout_start = Carbon::parse($request->input('checkout_date_start'))->startOfDay();
                $checkout_end = Carbon::parse($request->input('checkout_date_end',now()))->endOfDay();

                $actionlogassets = Actionlog::where('action_type','=', 'checkout')
                                              ->where('item_type', 'LIKE', '%Asset%',)
                                              ->whereBetween('action_date',[$checkout_start, $checkout_end])
                                                  ->pluck('item_id');

                $assets->whereIn('assets.id',$actionlogassets);
            }

            if (($request->filled('checkin_date_start'))) {
                $checkin_start = Carbon::parse($request->input('checkin_date_start'))->startOfDay();
                        // use today's date is `checkin_date_end` is not provided
                $checkin_end = Carbon::parse($request->input('checkin_date_end', now()))->endOfDay();

                $assets->whereBetween('assets.last_checkin', [$checkin_start, $checkin_end ]);
            }
            //last checkin is exporting, but currently is a date and not a datetime in the custom report ONLY.

            if (($request->filled('expected_checkin_start')) && ($request->filled('expected_checkin_end'))) {
                    $assets->whereBetween('assets.expected_checkin', [$request->input('expected_checkin_start'), $request->input('expected_checkin_end')]);
            }

            if (($request->filled('asset_eol_date_start')) && ($request->filled('asset_eol_date_end'))) {
                $assets->whereBetween('assets.asset_eol_date', [$request->input('asset_eol_date_start'), $request->input('asset_eol_date_end')]);
            }

            if (($request->filled('last_audit_start')) && ($request->filled('last_audit_end'))) {
                    $last_audit_start = Carbon::parse($request->input('last_audit_start'))->startOfDay();
                    $last_audit_end = Carbon::parse($request->input('last_audit_end'))->endOfDay();

                    $assets->whereBetween('assets.last_audit_date', [$last_audit_start, $last_audit_end]);
            }

            if (($request->filled('next_audit_start')) && ($request->filled('next_audit_end'))) {
                $assets->whereBetween('assets.next_audit_date', [$request->input('next_audit_start'), $request->input('next_audit_end')]);
            }

            if (($request->filled('last_updated_start')) && ($request->filled('last_updated_end'))) {
                $assets->whereBetween('assets.updated_at', [$request->input('last_updated_start'), $request->input('last_updated_end')]);
            }

            if ($request->filled('exclude_archived')) {
                $assets->notArchived();
            }
            if ($request->input('deleted_assets') == 'include_deleted') {
                $assets->withTrashed();
            }
            if ($request->input('deleted_assets') == 'only_deleted') {
                $assets->onlyTrashed();
            }

            Log::debug($assets->toSql());
            $assets->orderBy('assets.id', 'ASC')->chunk(500, function ($assets) use ($handle, $customfields, $request) {
            
                $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
                Log::debug('Walking results: '.$executionTime);
                $count = 0;

                $formatter = new EscapeFormula("`");

                foreach ($assets as $asset) {
                    $count++;
                    $row = [];

                    if ($request->filled('id')) {
                        $row[] = ($asset->id) ? $asset->id : '';
                    }

                    if ($request->filled('company')) {
                        $row[] = ($asset->company) ? $asset->company->name : '';
                    }

                    if ($request->filled('asset_name')) {
                        $row[] = ($asset->name) ? $asset->name : '';
                    }

                    if ($request->filled('asset_tag')) {
                        $row[] = ($asset->asset_tag) ? $asset->asset_tag : '';
                    }

                    if ($request->filled('model')) {
                        $row[] = ($asset->model) ? $asset->model->name : '';
                        $row[] = ($asset->model) ? $asset->model->model_number : '';
                    }

                    if ($request->filled('category')) {
                        $row[] = (($asset->model) && ($asset->model->category)) ? $asset->model->category->name : '';
                    }

                    if ($request->filled('manufacturer')) {
                        $row[] = ($asset->model && $asset->model->manufacturer) ? $asset->model->manufacturer->name : '';
                    }

                    if ($request->filled('serial')) {
                        $row[] = ($asset->serial) ? $asset->serial : '';
                    }

                    if ($request->filled('purchase_date')) {
                        $row[] = ($asset->purchase_date) ? $asset->purchase_date : '';
                    }

                    if ($request->filled('purchase_cost')) {
                        $row[] = ($asset->purchase_cost) ? Helper::formatCurrencyOutput($asset->purchase_cost) : '';
                    }

                    if ($request->filled('eol')) {
                        $row[] = ($asset->purchase_date != '') ? $asset->asset_eol_date : '';
                    }

                    if ($request->filled('warranty')) {
                        $row[] = ($asset->warranty_months) ? $asset->warranty_months : '';
                        $row[] = $asset->present()->warranty_expires();
                    }

                    if ($request->filled('depreciation')) {
                        $depreciation = $asset->getDepreciatedValue();
                        $diff = ($asset->purchase_cost - $depreciation);
                        $row[] = Helper::formatCurrencyOutput($depreciation);
                        $row[] = Helper::formatCurrencyOutput($diff);
                        $row[] = (($asset->depreciation) && ($asset->depreciated_date())) ? $asset->depreciated_date()->format('Y-m-d') : '';
                    }

                    if ($request->filled('order')) {
                        $row[] = ($asset->order_number) ? $asset->order_number : '';
                    }

                    if ($request->filled('supplier')) {
                        $row[] = ($asset->supplier) ? $asset->supplier->name : '';
                    }
                    
                    if ($request->filled('location')) {
                        $row[] = ($asset->location) ? $asset->location->display_name : '';
                    }

                    if ($request->filled('location_address')) {
                        $row[] = ($asset->location) ? $asset->location->address : '';
                        $row[] = ($asset->location) ? $asset->location->address2 : '';
                        $row[] = ($asset->location) ? $asset->location->city : '';
                        $row[] = ($asset->location) ? $asset->location->state : '';
                        $row[] = ($asset->location) ? $asset->location->country : '';
                        $row[] = ($asset->location) ? $asset->location->zip : '';
                    }

                    if ($request->filled('rtd_location')) {
                        $row[] = ($asset->defaultLoc) ? $asset->defaultLoc->display_name : '';
                    }

                    if ($request->filled('rtd_location_address')) {
                        $row[] = ($asset->defaultLoc) ? $asset->defaultLoc->address : '';
                        $row[] = ($asset->defaultLoc) ? $asset->defaultLoc->address2 : '';
                        $row[] = ($asset->defaultLoc) ? $asset->defaultLoc->city : '';
                        $row[] = ($asset->defaultLoc) ? $asset->defaultLoc->state : '';
                        $row[] = ($asset->defaultLoc) ? $asset->defaultLoc->country : '';
                        $row[] = ($asset->defaultLoc) ? $asset->defaultLoc->zip : '';
                    }

                    if ($request->filled('assigned_to')) {
                        $row[] = ($asset->checkedOutToUser() && $asset->assigned) ? $asset->assigned->display_name : '';
                        $row[] = ($asset->checkedOutToUser() && $asset->assigned) ? 'user' : $asset->assignedType();
                    }

                    if ($request->filled('username')) {
                        // Only works if we're checked out to a user, not anything else.
                        if ($asset->checkedOutToUser()) {
                            $row[] = ($asset->assignedto) ? $asset->assignedto->username : '';
                        } else {
                            $row[] = ''; // Empty string if unassigned
                        }
                    }

                    if ($request->filled('employee_num')) {
                        // Only works if we're checked out to a user, not anything else.
                        if ($asset->checkedOutToUser()) {
                            $row[] = ($asset->assignedto) ? $asset->assignedto->employee_num : '';
                        } else {
                            $row[] = ''; // Empty string if unassigned
                        }
                    }

                    if ($request->filled('manager')) {
                        if ($asset->checkedOutToUser()) {
                            $row[] = (($asset->assignedto) && ($asset->assignedto->manager)) ? $asset->assignedto->manager->present()->fullName() : '';
                        } else {
                            $row[] = ''; // Empty string if unassigned
                        }
                    }

                    if ($request->filled('department')) {
                        if ($asset->checkedOutToUser()) {
                            $row[] = (($asset->assignedto) && ($asset->assignedto->department)) ? $asset->assignedto->department->name : '';
                        } else {
                            $row[] = ''; // Empty string if unassigned
                        }
                    }

                    if ($request->filled('title')) {
                        if ($asset->checkedOutToUser()) {
                            $row[] = ($asset->assignedto) ? $asset->assignedto->jobtitle : '';
                        } else {
                            $row[] = ''; // Empty string if unassigned
                        }
                    }

                    if ($request->filled('phone')) {
                        if ($asset->checkedOutToUser()) {
                            $row[] = ($asset->assignedto) ? $asset->assignedto->phone : '';
                        } else {
                            $row[] = ''; // Empty string if unassigned
                        }
                    }

                    if ($request->filled('user_address')) {
                        if ($asset->checkedOutToUser()) {
                            $row[] = ($asset->assignedto) ? $asset->assignedto->address : '';
                        } else {
                            $row[] = ''; // Empty string if unassigned
                        }
                    }

                    if ($request->filled('user_city')) {
                        if ($asset->checkedOutToUser()) {
                            $row[] = ($asset->assignedto) ? $asset->assignedto->city : '';
                        } else {
                            $row[] = ''; // Empty string if unassigned
                        }
                    }

                    if ($request->filled('user_state')) {
                        if ($asset->checkedOutToUser()) {
                            $row[] = ($asset->assignedto) ? $asset->assignedto->state : '';
                        } else {
                            $row[] = ''; // Empty string if unassigned
                        }
                    }

                    if ($request->filled('user_country')) {
                        if ($asset->checkedOutToUser()) {
                            $row[] = ($asset->assignedto) ? $asset->assignedto->country : '';
                        } else {
                            $row[] = ''; // Empty string if unassigned
                        }
                    }

                    if ($request->filled('user_zip')) {
                        if ($asset->checkedOutToUser()) {
                            $row[] = ($asset->assignedto) ? $asset->assignedto->zip : '';
                        } else {
                            $row[] = ''; // Empty string if unassigned
                        }
                    }

                    if ($request->filled('status')) {
                        $row[] = ($asset->assetstatus) ? $asset->assetstatus->name.' ('.$asset->present()->statusMeta.')' : '';
                    }

                    if ($request->filled('checkout_date')) {
                        $row[] = ($asset->last_checkout) ? $asset->last_checkout : '';
                    }

                    if ($request->filled('checkin_date')) {
                        $row[] = ($asset->last_checkin)
                            ? Carbon::parse($asset->last_checkin)->format('Y-m-d')
                            : '';
                    }

                    if ($request->filled('expected_checkin')) {
                        $row[] = ($asset->expected_checkin) ? $asset->expected_checkin : '';
                    }

                    if ($request->filled('created_at')) {
                        $row[] = ($asset->created_at) ? $asset->created_at : '';
                    }

                    if ($request->filled('updated_at')) {
                        $row[] = ($asset->updated_at) ? $asset->updated_at : '';
                    }

                    if ($request->filled('deleted_at')) {
                        $row[] = ($asset->deleted_at) ? $asset->deleted_at : '';
                    }

                    if ($request->filled('last_audit_date')) {
                        $row[] = ($asset->last_audit_date) ? $asset->last_audit_date : '';
                    }

                    if ($request->filled('next_audit_date')) {
                        $row[] = ($asset->next_audit_date) ? $asset->next_audit_date : '';
                    }

                    if ($request->filled('notes')) {
                        $row[] = ($asset->notes) ? $asset->notes : '';
                    }

                    if ($request->filled('url')) {
                        $row[] = config('app.url').'/hardware/'.$asset->id;
                    }

                    foreach ($customfields as $customfield) {
                        $column_name = $customfield->db_column_name();
                        if ($request->filled($customfield->db_column_name())) {
                            $row[] = $asset->$column_name;
                        }
                    }

                    
                    // CSV_ESCAPE_FORMULAS is set to false in the .env
                    if (config('app.escape_formulas') === false) {
                        fputcsv($handle, $row);

                   // CSV_ESCAPE_FORMULAS is set to true or is not set in the .env
                    } else {
                        fputcsv($handle, $formatter->escapeRecord($row));
                    }

                    $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
                    Log::debug('-- Record '.$count.' Asset ID:'.$asset->id.' in '.$executionTime);
                }
            });

            // Close the output stream
            fclose($handle);
            $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
            Log::debug('-- SCRIPT COMPLETED IN '.$executionTime);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="custom-assets-report-'.date('Y-m-d-his').'.csv"',
        ]);

        return $response;
    }

    /**
     * getImprovementsReport
     *
     * @author  Vincent Sposato <vincent.sposato@gmail.com>
     * @version v1.0
     */
    public function getMaintenancesReport() : View
    {
        $this->authorize('reports.view');

        return view('reports.maintenances');
    }

    /**
     * exportImprovementsReport
     *
     * @author  Vincent Sposato <vincent.sposato@gmail.com>
     * @version v1.0
     */
    public function exportMaintenancesReport() : Response
    {
        $this->authorize('reports.view');
        // Grab all the improvements
        $Maintenances = Maintenance::with('asset', 'supplier')
                                             ->orderBy('created_at', 'DESC')
                                             ->get();

        $rows = [];

        $header = [
            trans('admin/hardware/table.asset_tag'),
            trans('admin/maintenances/table.asset_name'),
            trans('general.supplier'),
            trans('admin/maintenances/form.asset_maintenance_type'),
            trans('admin/maintenances/form.title'),
            trans('admin/maintenances/form.start_date'),
            trans('admin/maintenances/form.completion_date'),
            trans('admin/maintenances/form.asset_maintenance_time'),
            trans('admin/maintenances/form.cost'),
        ];

        $header = array_map('trim', $header);
        $rows[] = implode(',', $header);

        foreach ($Maintenances as $maintenance) {
            $row = [];
            $row[] = str_replace(',', '&#44;', e($maintenance->asset->asset_tag));
            $row[] = str_replace(',', '&#44;', e($maintenance->asset->name));
            $row[] = str_replace(',', '&#44;', e($maintenance->supplier->name));
            $row[] = e($maintenance->improvement_type);
            $row[] = e($maintenance->name);
            $row[] = e($maintenance->start_date);
            $row[] = e($maintenance->completion_date);
            if (is_null($maintenance->asset_maintenance_time)) {
                $improvementTime = (int) Carbon::now()
                    ->diffInDays(Carbon::parse($maintenance->start_date), true);
            } else {
                $improvementTime = (int) $maintenance->asset_maintenance_time;
            }
            $row[]  = $improvementTime;
            $row[]  = trans('general.currency') . Helper::formatCurrencyOutput($maintenance->cost);
            $rows[] = implode(',', $row);
        }

        // spit out a csv
        $csv      = implode("\n", $rows);
        $response = response()->make($csv, 200);
        $response->header('Content-Type', 'text/csv');
        $response->header('Content-disposition', 'attachment;filename=report.csv');

        return $response;
    }

    /**
     * getAssetAcceptanceReport
     *
     * @author  Vincent Sposato <vincent.sposato@gmail.com>
     * @version v1.0
     */
    public function getAssetAcceptanceReport($deleted = false): View
    {
        $this->authorize('reports.view');
        $showDeleted = $deleted == 'deleted';

        $query = CheckoutAcceptance::Pending()
            ->with([
                'checkoutable' => function (MorphTo $query) {
                    $query->withTrashed()->morphWith([
                        Asset::class => ['model.category', 'assignedTo', 'company'],
                        Accessory::class => ['category', 'checkouts', 'company'],
                        LicenseSeat::class => ['user', 'license'],
                        Component::class => ['assignedTo', 'company'],
                        Consumable::class => ['company'],
                    ]);
                },
                'assignedTo' => function ($query) {
                    $query->withTrashed();
                },
            ])->orderByDesc('checkout_acceptances.created_at');

        if ($showDeleted) {
            $query->withTrashed();
        }

        // Get acceptances and generate tokens for those missing them
        $acceptances = $query->get();
        foreach ($acceptances as $acceptance) {
            if (!$acceptance->token || !$acceptance->isTokenValid()) {
                $acceptance->generateToken();
            }
        }

        $itemsForReport = $acceptances
            ->filter(fn ($unaccepted) => $unaccepted->checkoutable)
            ->map(fn ($unaccepted) => Checkoutable::fromAcceptance($unaccepted));

        $eulaStats = [
            'total' => $acceptances->count(),
            'critical' => $acceptances->filter(fn ($a) => $a->getDaysPending() > 30)->count(),
            'warning' => $acceptances->filter(fn ($a) => $a->getDaysPending() > 7 && $a->getDaysPending() <= 30)->count(),
            'ok' => $acceptances->filter(fn ($a) => $a->getDaysPending() <= 7)->count(),
            'oldest_days' => $acceptances->max(fn ($a) => $a->getDaysPending()) ?? 0,
        ];

        return view('reports/unaccepted_assets', compact('itemsForReport', 'showDeleted', 'eulaStats'));
    }

    /**
     * sentAssetAcceptanceReminder
     *
     * @param integer|null $acceptanceId
     * @version v1.0
     */
    public function sentAssetAcceptanceReminder(Request $request) : RedirectResponse
    {
        $this->authorize('reports.view');

        if (!$acceptance = CheckoutAcceptance::pending()->find($request->input('acceptance_id'))) {
            Log::debug('No pending acceptances');
            // Redirect to the unaccepted assets report page with error
            return redirect()->route('reports/unaccepted_assets')->with('error', trans('general.bad_data'));
        }

        $assetItem = $acceptance->checkoutable;

        if (! $assetItem) {
            return redirect()->route('reports/unaccepted_assets')->with('error', trans('general.bad_data'));
        }

        if (is_null($acceptance->created_at)){
            Log::debug('No acceptance created_at');
            return redirect()->route('reports/unaccepted_assets')->with('error', trans('general.bad_data'));
        } else {
            // License checkout history is recorded against the license, not
            // the seat. Match the acceptance recipient as well as its time.
            $logOwner = $assetItem instanceof LicenseSeat ? $assetItem->license : $assetItem;
            $logItem_res = Actionlog::query()
                ->where('action_type', 'checkout')
                ->where('item_id', $logOwner->id)
                ->where('item_type', get_class($logOwner))
                ->where('target_id', $acceptance->assigned_to_id)
                ->where('target_type', \App\Models\User::class)
                ->where('created_at', '=', $acceptance->created_at)->get();

            if ($logItem_res->isEmpty()){
                Log::debug('Acceptance date mismatch');
                return redirect()->route('reports/unaccepted_assets')->with('error', trans('general.bad_data'));
            }
            $logItem = $logItem_res[0];
        }
        $email = $acceptance->assignedTo?->email;
        $locale = $acceptance->assignedTo?->locale;

        if (is_null($email) || $email === '') {
            return redirect()->route('reports/unaccepted_assets')->with('error', trans('general.no_email'));
        }

        $mailClass = match (true) {
            $assetItem instanceof Asset => CheckoutAssetMail::class,
            $assetItem instanceof Accessory => CheckoutAccessoryMail::class,
            $assetItem instanceof Consumable => CheckoutConsumableMail::class,
            $assetItem instanceof LicenseSeat => CheckoutLicenseMail::class,
            default => null,
        };
        if (! $mailClass || ! $logItem->adminuser) {
            return redirect()->route('reports/unaccepted_assets')->with('error', trans('general.bad_data'));
        }
        Mail::to($email)->send((new $mailClass($assetItem, $acceptance->assignedTo, $logItem->adminuser, $acceptance, $logItem->note, firstTimeSending: false))->locale($locale));

        return redirect()->route('reports/unaccepted_assets')->with('success', trans('admin/reports/general.reminder_sent'));
    }

    /**
     * sentAssetAcceptanceReminder
     *
     * @param integer|null $acceptanceId
     * @version v1.0
     */
    public function deleteAssetAcceptance($acceptanceId = null) : RedirectResponse
    {
        $this->authorize('reports.view');

        if (!$acceptance = CheckoutAcceptance::pending()->find($acceptanceId)) {
            // Redirect to the unaccepted assets report page with error
            return redirect()->route('reports/unaccepted_assets')->with('error', trans('general.bad_data'));
        }

        if($acceptance->delete()) {
            return redirect()->route('reports/unaccepted_assets')->with('success', trans('admin/reports/general.acceptance_deleted'));
        } else {
            return redirect()->route('reports/unaccepted_assets')->with('error', trans('general.deletion_failed'));
        }
    }

    /**
     * Exports the AssetAcceptance report to CSV
     *
     * @author  Vincent Sposato <vincent.sposato@gmail.com>
     * @version v1.0
     */
    public function postAssetAcceptanceReport($deleted = false) : Response
    {
        $this->authorize('reports.view');
        $showDeleted = $deleted == 'deleted';

        /**
         * Get all assets with pending checkout acceptances
         */
        if($showDeleted) {
            $acceptances = CheckoutAcceptance::pending()->where('checkoutable_type', 'App\Models\Asset')->withTrashed()->with(['assignedTo', 'checkoutable.assignedTo', 'checkoutable.model.category'])->get();
        } else {
            $acceptances = CheckoutAcceptance::pending()->where('checkoutable_type', 'App\Models\Asset')->with(['assignedTo', 'checkoutable.assignedTo', 'checkoutable.model.category'])->get();
        }

        // Generate tokens for acceptances that don't have them
        foreach ($acceptances as $acceptance) {
            if (!$acceptance->token || !$acceptance->isTokenValid()) {
                $acceptance->generateToken();
            }
        }

        $assetsForReport = $acceptances
            ->filter(function($acceptance) {
                return $acceptance->checkoutable_type == 'App\Models\Asset';
            })
            ->map(function($acceptance) {
                return [
                    'assetItem' => $acceptance->checkoutable, 
                    'acceptance' => $acceptance,
                    'days_pending' => $acceptance->getDaysPending(),
                    'priority_class' => $acceptance->getPriorityClass(),
                    'token' => $acceptance->token,
                ];
            });

        $rows = [];

        $header = [
            trans('general.category'),
            trans('admin/hardware/form.model'),
            trans('admin/hardware/form.name'),
            trans('admin/hardware/table.asset_tag'),
            trans('admin/hardware/table.checkoutto'),
            'Data de Cria��o',
            'Dias Pendentes',
        ];

        $header = array_map('trim', $header);
        $rows[] = implode(',', $header);

        foreach ($assetsForReport as $item) {

            if ($item['assetItem'] != null){
            
                $row    = [ ];
                $row[]  = str_replace(',', '&#44;', e($item['assetItem']->model->category->name));
                $row[]  = str_replace(',', '&#44;', e($item['assetItem']->model->name));
                $row[]  = str_replace(',', '&#44;', e($item['assetItem']->name));
                $row[]  = str_replace(',', '&#44;', e($item['assetItem']->asset_tag));
                $row[]  = str_replace(',', '&#44;', e(($item['acceptance']->assignedTo) ? $item['acceptance']->assignedTo->present()->name() : trans('admin/reports/general.deleted_user')));
                $row[]  = str_replace(',', '&#44;', e($item['acceptance']->created_at ? $item['acceptance']->created_at->format('d/m/Y H:i') : 'N/A'));
                $row[]  = str_replace(',', '&#44;', e($item['acceptance']->getDaysPending() . ' dias'));
                $rows[] = implode(',', $row);
            }
        }

        // spit out a csv
        $csv      = implode("\n", $rows);
        $response = response()->make($csv, 200);
        $response->header('Content-Type', 'text/csv');
        $response->header('Content-disposition', 'attachment;filename=report.csv');

        return $response;
    }

    /**
     * getCheckedOutAssetsRequiringAcceptance
     *
     * @param $modelsInCategoriesThatRequireAcceptance
     *
     * @return array
     * @author  Vincent Sposato <vincent.sposato@gmail.com>
     * @version v1.0
     */
    protected function getCheckedOutAssetsRequiringAcceptance($modelsInCategoriesThatRequireAcceptance) : View
    {
        $this->authorize('reports.view');
        $assets = Asset::deployed()
                        ->inModelList($modelsInCategoriesThatRequireAcceptance)
                        ->select('id')
                        ->get()
                        ->toArray();

        return array_pluck($assets, 'id');
    }

    /**
     * getModelsInCategoriesThatRequireAcceptance
     *
     * @param $assetCategoriesRequiringAcceptance
     * @return array
     * @author  Vincent Sposato <vincent.sposato@gmail.com>
     * @version v1.0
     */
    protected function getModelsInCategoriesThatRequireAcceptance($assetCategoriesRequiringAcceptance) : array
    {
        $this->authorize('reports.view');

        return array_pluck(AssetModel::inCategory($assetCategoriesRequiringAcceptance)
                                 ->select('id')
                                 ->get()
                                 ->toArray(), 'id');
    }

    /**
     * getCategoriesThatRequireAcceptance
     *
     * @return array
     * @author  Vincent Sposato <vincent.sposato@gmail.com>
     * @version v1.0
     */
    protected function getCategoriesThatRequireAcceptance() : array
    {
        $this->authorize('reports.view');

        return array_pluck(Category::requiresAcceptance()
                                    ->select('id')
                                    ->get()
                                    ->toArray(), 'id');
    }

    /**
     * getAssetsCheckedOutRequiringAcceptance
     *
     * @author  Vincent Sposato <vincent.sposato@gmail.com>
     * @version v1.0
     */
    protected function getAssetsCheckedOutRequiringAcceptance() : array
    {
        $this->authorize('reports.view');

        return $this->getCheckedOutAssetsRequiringAcceptance(
            $this->getModelsInCategoriesThatRequireAcceptance($this->getCategoriesThatRequireAcceptance())
        );
    }

    /**
     * Display EULA signatures report
     *
     * @author [Kiro AI]
     * @since [v1.0]
     * @param Request $request
     * @return View
     */
    public function getEulaSignaturesReport(Request $request) : View
    {
        // Validar permiss�es do usu�rio
        $this->authorize('reports.view');

        // Validar e sanitizar inputs recebidos
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'device_type' => 'nullable|in:desktop,mobile',
            'item_type' => 'nullable|string|max:100',
        ]);

        // Capturar par�metros de filtro
        $filters = [
            'search' => $request->input('search'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'device_type' => $request->input('device_type'),
            'item_type' => $request->input('item_type'),
        ];

        // Aplicar sanitiza��o adicional nos dados
        if (!empty($filters['search'])) {
            $filters['search'] = trim(strip_tags($filters['search']));
        }

        if (!empty($filters['item_type'])) {
            $filters['item_type'] = trim(strip_tags($filters['item_type']));
        }

        // Buscar assinaturas com pagina��o
        // Query com eager loading (assignedTo, checkoutable)
        $signatures = CheckoutAcceptance::with([
            'assignedTo:id,first_name,last_name,email,employee_num',
            'checkoutable'
        ])
        ->completed();

        // Aplicar filtros din�micos
        
        // Filtro por busca de nome de usu�rio
        if (!empty($filters['search'])) {
            $signatures->whereHas('assignedTo', function ($query) use ($filters) {
                $query->where(function ($q) use ($filters) {
                    $q->where('first_name', 'LIKE', '%' . $filters['search'] . '%')
                      ->orWhere('last_name', 'LIKE', '%' . $filters['search'] . '%')
                      ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $filters['search'] . '%']);
                });
            });
        }

        // Filtro por per�odo (data in�cio e data fim)
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $signatures->byDateRange($filters['date_from'], $filters['date_to']);
        } elseif (!empty($filters['date_from'])) {
            $signatures->byDateRange($filters['date_from'], null);
        } elseif (!empty($filters['date_to'])) {
            $signatures->byDateRange(null, $filters['date_to']);
        }

        // Filtro por tipo de dispositivo
        if (!empty($filters['device_type'])) {
            $signatures->byDeviceType($filters['device_type']);
        }

        // Filtro por tipo de item (Asset, License, Accessory, etc)
        if (!empty($filters['item_type'])) {
            $signatures->where('checkoutable_type', 'LIKE', '%' . $filters['item_type'] . '%');
        }

        // Ordena��o por accepted_at DESC
        $signatures->orderBy('accepted_at', 'DESC');

        // Pagina��o de 25 itens por p�gina
        $signatures = $signatures->paginate(25)->appends($request->except('page'));

        // Calcular estat�sticas com cache de 5 minutos
        $cacheKey = 'eula_signatures_stats_' . md5(json_encode($filters));
        $stats = Cache::remember($cacheKey, 300, function () use ($filters) {
            // Query base para estat�sticas (respeitando filtros ativos)
            $baseQuery = CheckoutAcceptance::completed();

            // Aplicar os mesmos filtros da listagem
            if (!empty($filters['search'])) {
                $baseQuery->whereHas('assignedTo', function ($query) use ($filters) {
                    $query->where(function ($q) use ($filters) {
                        $q->where('first_name', 'LIKE', '%' . $filters['search'] . '%')
                          ->orWhere('last_name', 'LIKE', '%' . $filters['search'] . '%')
                          ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $filters['search'] . '%']);
                    });
                });
            }

            if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
                $baseQuery->byDateRange($filters['date_from'], $filters['date_to']);
            } elseif (!empty($filters['date_from'])) {
                $baseQuery->byDateRange($filters['date_from'], null);
            } elseif (!empty($filters['date_to'])) {
                $baseQuery->byDateRange(null, $filters['date_to']);
            }

            if (!empty($filters['device_type'])) {
                $baseQuery->byDeviceType($filters['device_type']);
            }

            if (!empty($filters['item_type'])) {
                $baseQuery->where('checkoutable_type', 'LIKE', '%' . $filters['item_type'] . '%');
            }

            // Total de assinaturas completas (com filtros aplicados)
            $total = $baseQuery->count();

            // Assinaturas nos �ltimos 7 dias (com filtros aplicados)
            $last7Days = (clone $baseQuery)
                ->where('accepted_at', '>=', Carbon::now()->subDays(7))
                ->count();

            // Contagem por tipo de dispositivo (desktop/mobile)
            $desktopCount = (clone $baseQuery)
                ->where('signature_device_type', 'desktop')
                ->count();

            $mobileCount = (clone $baseQuery)
                ->where('signature_device_type', 'mobile')
                ->count();

            // Assinaturas com/sem geolocaliza��o
            $withGeolocation = (clone $baseQuery)
                ->whereNotNull('signature_latitude')
                ->whereNotNull('signature_longitude')
                ->count();

            $withoutGeolocation = $total - $withGeolocation;

            return [
                'total' => $total,
                'last_7_days' => $last7Days,
                'desktop_count' => $desktopCount,
                'mobile_count' => $mobileCount,
                'with_geolocation' => $withGeolocation,
                'without_geolocation' => $withoutGeolocation,
            ];
        });

        return view('reports.eula-signatures', compact('signatures', 'filters', 'stats'));
    }

    /**
     * Show EULA signature detail
     *
     * @param int $id
     * @return View
     */
    public function showEulaSignatureDetail($id): View
    {
        $this->authorize('reports.view');

        $signature = CheckoutAcceptance::with(['assignedTo', 'checkoutable'])
            ->whereNotNull('signature_filename')
            ->findOrFail($id);

        return view('reports.eula-signature-detail', compact('signature'));
    }

    /**
     * Export EULA signature as PDF
     *
     * @param int $id
     * @return Response
     */
    public function exportEulaSignaturePdf($id): Response
    {
        // 7.1 Validar permiss�es e buscar assinatura
        $this->authorize('reports.view');

        // Buscar assinatura com relacionamentos
        $signature = CheckoutAcceptance::with(['assignedTo', 'checkoutable.model'])
            ->whereNotNull('signature_filename')
            ->whereNotNull('accepted_at')
            ->findOrFail($id);

        // Buscar configurações de marca e timezone
        $settings = Setting::getSettings();
        
        // Configurar timezone padrão brasileiro
        config(['app.timezone' => 'America/Sao_Paulo']);
        date_default_timezone_set('America/Sao_Paulo');
        
        // Buscar o EULA que foi aceito (da coluna stored_eula)
        $eulaText = null;
        
        // Debug: verificar o que temos na assinatura
        \Log::info('Debug EULA PDF - Signature ID: ' . $signature->id);
        \Log::info('Debug EULA PDF - stored_eula exists: ' . ($signature->stored_eula ? 'YES' : 'NO'));
        \Log::info('Debug EULA PDF - stored_eula length: ' . strlen($signature->stored_eula ?? ''));
        
        if ($signature->stored_eula) {
            // Usar o EULA armazenado na assinatura (markdown)
            $eulaText = \App\Helpers\Helper::parseEscapedMarkedown($signature->stored_eula);
            \Log::info('Debug EULA PDF - Parsed EULA length: ' . strlen($eulaText ?? ''));
        } elseif ($signature->checkoutable) {
            // Fallback: usar EULA padrão se não houver stored_eula
            $eulaText = Setting::getDefaultEula();
            \Log::info('Debug EULA PDF - Using default EULA');
        }

        try {
            // 7.3 Configurar TCPDF
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Definir metadados do PDF
            $pdf->SetCreator('Snipe-IT');
            $pdf->SetAuthor('Sistema Snipe-IT');
            $pdf->SetTitle('Comprovante de Assinatura Digital - EULA');
            $pdf->SetSubject('Comprovante de Assinatura EULA');
            $pdf->SetKeywords('EULA, Assinatura, Digital, Comprovante');

            // Configurar margens e orienta��o
            $pdf->SetMargins(15, 27, 15);
            $pdf->SetHeaderMargin(5);
            $pdf->SetFooterMargin(10);
            $pdf->SetAutoPageBreak(TRUE, 25);

            // Adicionar p�gina
            $pdf->AddPage();

            // 7.4 Gerar conte�do do PDF
            // Renderizar view HTML com configurações de marca e EULA
            $html = view('reports.pdf.eula-signature', compact('signature', 'settings', 'eulaText'))->render();
            
            // Converter HTML para PDF com writeHTML()
            $pdf->writeHTML($html, true, false, true, false, '');

            $this->appendSignatureImageToPdf($pdf, $signature);
            $this->appendPdfFooterNote($pdf, $signature);

            // 7.5 Retornar PDF para download
            // Nome do arquivo: eula_signature_{user_id}_{timestamp}.pdf
            $filename = 'eula_signature_' . $signature->assigned_to_id . '_' . time() . '.pdf';
            
            // Log de gera��o bem-sucedida
            Log::info('PDF de assinatura EULA gerado com sucesso', [
                'signature_id' => $id,
                'user_id' => $signature->assigned_to_id,
                'filename' => $filename,
                'generated_by' => auth()->id()
            ]);

            $pdfContent = $pdf->Output($filename, 'S');

            // Output com modo 'D' (download)
            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => strlen($pdfContent)
            ]);

        } catch (Exception $e) {
            // Tratamento de erros com mensagem amig�vel
            Log::error('Erro ao gerar PDF de assinatura EULA', [
                'signature_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Erro ao gerar PDF da assinatura. Por favor, tente novamente.');
        }
    }

    /**
     * Export multiple EULA signatures as ZIP file
     *
     * @param Request $request
     * @return Response
     */
    public function exportEulaSignaturesBatch(Request $request): Response
    {
        $this->authorize('reports.view');

        try {
            // 8.1 Validar requisi��o
            $request->validate([
                'ids' => 'required|array|max:100',
                'ids.*' => 'integer|exists:checkout_acceptances,id'
            ]);

            $signatureIds = $request->input('ids', []);
            
            if (empty($signatureIds)) {
                return redirect()->back()
                    ->with('error', 'Nenhuma assinatura selecionada para exporta��o.');
            }

            if (count($signatureIds) > 100) {
                return redirect()->back()
                    ->with('error', 'M�ximo de 100 assinaturas podem ser exportadas por vez.');
            }

            // Verificar se IDs existem no banco
            $existingSignatures = CheckoutAcceptance::whereIn('id', $signatureIds)
                ->completed()
                ->count();

            if ($existingSignatures !== count($signatureIds)) {
                return redirect()->back()
                    ->with('error', 'Algumas assinaturas selecionadas n�o foram encontradas ou n�o est�o completas.');
            }

            // 8.2 Decidir estrat�gia de processamento
            $signatureCount = count($signatureIds);
            
            if ($signatureCount <= 10) {
                // Processar sincronamente
                return $this->processBatchExportSync($signatureIds);
            } else {
                // Para mais de 10 assinaturas, ainda processar sincronamente
                // mas com timeout maior e mais mem�ria
                ini_set('max_execution_time', 300); // 5 minutos
                ini_set('memory_limit', '512M');
                
                return $this->processBatchExportSync($signatureIds);
            }

        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->with('error', 'Dados inv�lidos fornecidos.');
        } catch (Exception $e) {
            Log::error('Error in batch EULA signature export', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Erro interno do servidor. Por favor, tente novamente.');
        }
    }

    /**
     * Process batch export synchronously
     *
     * @param array $signatureIds
     * @return Response
     */
    private function processBatchExportSync(array $signatureIds): Response
    {
        try {
            // 8.3 Gerar PDFs individuais
            $tempDir = storage_path('app/temp/eula_batch_' . time());
            
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $pdfFiles = [];
            $errors = [];

            foreach ($signatureIds as $signatureId) {
                try {
                    $signature = CheckoutAcceptance::with(['assignedTo', 'checkoutable'])
                        ->completed()
                        ->findOrFail($signatureId);

                    // Gerar PDF individual
                    $pdfContent = $this->generateSignaturePdfContent($signature);
                    
                    // Salvar PDF temporariamente
                    $filename = 'eula_signature_' . $signature->assignedTo->id . '_' . $signature->id . '.pdf';
                    $filepath = $tempDir . '/' . $filename;
                    
                    file_put_contents($filepath, $pdfContent);
                    $pdfFiles[] = [
                        'path' => $filepath,
                        'name' => $filename
                    ];

                } catch (Exception $e) {
                    $errors[] = "Erro ao gerar PDF para assinatura ID {$signatureId}: " . $e->getMessage();
                    Log::error('Error generating individual PDF in batch export', [
                        'signature_id' => $signatureId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if (empty($pdfFiles)) {
                // Limpar diret�rio tempor�rio
                $this->cleanupTempDirectory($tempDir);
                
                return redirect()->back()
                    ->with('error', 'N�o foi poss�vel gerar nenhum PDF. Erros: ' . implode('; ', $errors));
            }

            // 8.4 Criar arquivo ZIP
            $zipFilename = 'eula_signatures_' . time() . '.zip';
            $zipPath = $tempDir . '/' . $zipFilename;

            $zip = new ZipArchive();
            $zipResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            if ($zipResult !== TRUE) {
                $this->cleanupTempDirectory($tempDir);
                
                return redirect()->back()
                    ->with('error', 'Erro ao criar arquivo ZIP: ' . $zipResult);
            }

            // Adicionar cada PDF ao ZIP
            foreach ($pdfFiles as $pdfFile) {
                $zip->addFile($pdfFile['path'], $pdfFile['name']);
            }

            $zip->close();

            // Verificar se ZIP foi criado com sucesso
            if (!file_exists($zipPath)) {
                $this->cleanupTempDirectory($tempDir);
                
                return redirect()->back()
                    ->with('error', 'Erro ao finalizar arquivo ZIP.');
            }

            // 8.5 Retornar ZIP para download
            $zipSize = filesize($zipPath);
            
            // Log de exporta��o em lote
            Log::info('EULA signatures batch export completed', [
                'user_id' => auth()->id(),
                'signature_count' => count($pdfFiles),
                'errors_count' => count($errors),
                'zip_size' => $zipSize,
                'signature_ids' => $signatureIds
            ]);

            // Retornar arquivo para download e limpar ap�s envio
            return response()->download($zipPath, $zipFilename)->deleteFileAfterSend(true);

        } catch (Exception $e) {
            // Limpar diret�rio tempor�rio em caso de erro
            if (isset($tempDir)) {
                $this->cleanupTempDirectory($tempDir);
            }

            Log::error('Error in processBatchExportSync', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Erro ao processar exporta��o em lote: ' . $e->getMessage());
        }
    }

    /**
     * Generate PDF content for a signature
     *
     * @param CheckoutAcceptance $signature
     * @return string
     */
    private function generateSignaturePdfContent(CheckoutAcceptance $signature): string
    {
        // Usar o mesmo m�todo do PDF individual, mas retornar conte�do ao inv�s de download
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Configura��es do PDF
        $pdf->SetCreator('Snipe-IT');
        $pdf->SetAuthor('Sistema Snipe-IT');
        $pdf->SetTitle('Comprovante de Assinatura Digital - EULA');
        $pdf->SetSubject('Assinatura EULA');
        $pdf->SetKeywords('EULA, Assinatura, Digital, Snipe-IT');
        
        // Configurar margens
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        
        // Remover header e footer padr�o
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Adicionar p�gina
        $pdf->AddPage();
        
        // Gerar conte�do HTML
        $html = view('reports.pdf.eula-signature', compact('signature'))->render();
        
        // Escrever HTML no PDF
        $pdf->writeHTML($html, true, false, true, false, '');

        $this->appendSignatureImageToPdf($pdf, $signature);
        $this->appendPdfFooterNote($pdf, $signature);
        
        // Retornar conte�do do PDF
        return $pdf->Output('', 'S');
    }

    /**
     * Add the signature image directly to the PDF.
     */
    private function appendSignatureImageToPdf(TCPDF $pdf, CheckoutAcceptance $signature): void
    {
        if (!$signature->signature_filename) {
            Log::info('Skipping PDF signature image because signature_filename is empty', [
                'signature_id' => $signature->id,
            ]);

            return;
        }

        $signaturePath = $this->resolveSignatureImagePath($signature->signature_filename);

        Log::info('Resolved signature image path for PDF export', [
            'signature_id' => $signature->id,
            'filename' => $signature->signature_filename,
            'resolved_path' => $signaturePath,
        ]);

        if ($signaturePath === null) {
            Log::warning('Signature image file not found for PDF export', [
                'signature_id' => $signature->id,
                'filename' => $signature->signature_filename,
            ]);

            return;
        }

        $imageWidth = 90.0;
        $imageHeight = 28.0;
        $imageSize = @getimagesize($signaturePath);

        if (is_array($imageSize) && !empty($imageSize[0]) && !empty($imageSize[1])) {
            $ratio = $imageSize[1] / max((float) $imageSize[0], 1.0);
            $imageHeight = min(35.0, max(18.0, $imageWidth * $ratio));
        }

        $requiredHeight = 18.0 + $imageHeight;

        if (($pdf->GetY() + $requiredHeight) > ($pdf->getPageHeight() - $pdf->getBreakMargin())) {
            $pdf->AddPage();
        }

        $pdf->Ln(4);
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(0, 0, 'Assinatura Digital', 0, 1, 'C');
        $pdf->Ln(3);

        $x = ($pdf->getPageWidth() - $imageWidth) / 2;
        $y = $pdf->GetY();

        try {
            $pdf->Image($signaturePath, $x, $y, $imageWidth, $imageHeight, '', '', '', false, 300, '', false, false, 0);
            $pdf->SetY($y + $imageHeight + 4);

            Log::info('Signature image added to PDF successfully', [
                'signature_id' => $signature->id,
                'image_path' => $signaturePath,
                'x' => $x,
                'y' => $y,
                'width' => $imageWidth,
                'height' => $imageHeight,
                'page' => $pdf->getPage(),
            ]);
        } catch (Exception $e) {
            Log::warning('Could not add signature image to PDF', [
                'signature_id' => $signature->id,
                'image_path' => $signaturePath,
                'error' => $e->getMessage(),
            ]);
        }

        $pdf->SetFont('dejavusans', '', 10);
    }

    /**
     * Add the footer note after the signature block.
     */
    private function appendPdfFooterNote(TCPDF $pdf, CheckoutAcceptance $signature): void
    {
        if (($pdf->GetY() + 16) > ($pdf->getPageHeight() - $pdf->getBreakMargin())) {
            $pdf->AddPage();
        }

        $generatedAt = now()->timezone('America/Sao_Paulo')->format('d/m/Y \à\s H:i:s');
        $footerHtml = '
            <div style="font-size:7pt;color:#95a5a6;text-align:center;margin-top:10px;border-top:1px solid #ecf0f1;padding-top:5px;">
                Este documento foi gerado automaticamente pelo sistema Snipe-IT.
                A assinatura digital deste registro possui validade como comprovante de aceite do Termo de Uso.
                <br/>
                Registro #' . e($signature->id) . ' &mdash; Gerado em ' . e($generatedAt) . '
            </div>';

        $pdf->writeHTML($footerHtml, true, false, true, false, '');
    }

    /**
     * Resolve the local path for a signature image.
     */
    private function resolveSignatureImagePath(string $filename): ?string
    {
        $candidates = [
            storage_path('private_uploads/signatures/' . $filename),
            config('app.private_uploads') . '/signatures/' . $filename,
        ];

        foreach (array_unique($candidates) as $candidate) {
            if (is_string($candidate) && file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Clean up temporary directory
     *
     * @param string $tempDir
     * @return void
     */
    private function cleanupTempDirectory(string $tempDir): void
    {
        try {
            if (is_dir($tempDir)) {
                $files = glob($tempDir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($tempDir);
            }
        } catch (Exception $e) {
            Log::warning('Could not cleanup temporary directory', [
                'temp_dir' => $tempDir,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Export all EULA signatures (alias for batch export)
     *
     * @param Request $request
     * @return Response
     */
    public function exportEulaSignatures(Request $request): Response
    {
        $this->authorize('reports.view');

        // Get all completed signature IDs
        $signatureIds = CheckoutAcceptance::completed()
            ->pluck('id')
            ->toArray();

        if (empty($signatureIds)) {
            return redirect()->back()
                ->with('error', 'Nenhuma assinatura EULA encontrada para exportação.');
        }

        // Use the existing batch export method
        $request->merge(['ids' => $signatureIds]);
        return $this->exportEulaSignaturesBatch($request);
    }

    /**
     * Serve signature image files
     *
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function getSignatureImage($filename): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('reports.view');

        // Validate filename to prevent directory traversal
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
            abort(404, 'Invalid filename');
        }

        // Tentar path principal (volume Docker via symlink) e fallback via config
        $path = storage_path('private_uploads/signatures/' . $filename);
        if (!file_exists($path)) {
            $path = config('app.private_uploads') . '/signatures/' . $filename;
        }

        if (!file_exists($path)) {
            abort(404, 'Signature image not found');
        }

        // Get file info
        $mimeType = mime_content_type($path);
        $fileSize = filesize($path);

        // Validate it's an image
        if (!str_starts_with($mimeType, 'image/')) {
            abort(404, 'Invalid file type');
        }

        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Content-Length' => $fileSize,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Get EULA signature details as JSON for modal
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getEulaSignatureJson($id): JsonResponse
    {
        $this->authorize('reports.view');

        try {
            // Primeiro, vamos verificar se o registro existe
            $signature = CheckoutAcceptance::find($id);
            
            if (!$signature) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assinatura não encontrada'
                ], 404);
            }

            // Carregar relacionamentos de forma segura
            $signature->load(['assignedTo', 'checkoutable']);

            // Format data for modal de forma mais segura
            $data = [
                'id' => $signature->id,
                'user_name' => 'N/A',
                'user_cpf' => null,
                'user_email' => 'N/A',
                'user_id' => $signature->assigned_to_id,
                'item_type' => 'N/A',
                'item_name' => 'N/A',
                'item_asset_tag' => null,
                'item_id' => $signature->checkoutable_id,
                'accepted_at' => $signature->accepted_at ? $signature->accepted_at->format('Y-m-d H:i:s') : null,
                'device_type' => $signature->signature_device_type ?? 'N/A',
                'signature_ip' => $signature->signature_ip ?? 'N/A',
                'signature_latitude' => $signature->signature_latitude,
                'signature_longitude' => $signature->signature_longitude,
                'signature_filename' => $signature->signature_filename,
                'created_at' => $signature->created_at ? $signature->created_at->format('Y-m-d H:i:s') : null,
            ];

            // Tentar obter dados do usuário de forma segura
            if ($signature->assignedTo) {
                try {
                    $user = $signature->assignedTo;
                    $data['user_name'] = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'N/A';
                    $data['user_email'] = $user->email ?? 'N/A';
                    $data['user_cpf'] = $user->employee_num ?? null;
                } catch (\Exception $userError) {
                    // Silently handle user data errors
                }
            }

            // Tentar obter dados do item de forma segura
            if ($signature->checkoutable) {
                try {
                    $item = $signature->checkoutable;
                    $data['item_type'] = class_basename(get_class($item));
                    $data['item_name'] = $item->name ?? 'N/A';
                    
                    if (property_exists($item, 'asset_tag')) {
                        $data['item_asset_tag'] = $item->asset_tag;
                    }
                } catch (\Exception $itemError) {
                    // Silently handle item data errors
                }
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro em getEulaSignatureJson: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor'
            ], 500);
        }
    }

    /**
     * API endpoint for EULA signatures bootstrap-table (server-side pagination)
     * Returns JSON: {total: N, rows: [...]}
     */
    public function getEulaSignaturesApiIndex(Request $request): JsonResponse
    {
        $this->authorize('reports.view');

        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', 25);
        $sort = $request->input('sort', 'accepted_at');
        $order = $request->input('order', 'desc');
        $search = $request->input('search', '');

        // Sanitize sort column to prevent SQL injection
        $allowedSorts = ['id', 'accepted_at', 'created_at', 'signature_device_type', 'signature_ip', 'checkoutable_type'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'accepted_at';
        }
        $order = strtolower($order) === 'asc' ? 'asc' : 'desc';

        $query = CheckoutAcceptance::with([
            'assignedTo:id,first_name,last_name,email,employee_num',
            'checkoutable'
        ])->completed();

        // Search filter
        if (!empty($search)) {
            $search = trim(strip_tags($search));
            $query->where(function ($q) use ($search) {
                $q->whereHas('assignedTo', function ($uq) use ($search) {
                    $uq->where('first_name', 'LIKE', '%' . $search . '%')
                        ->orWhere('last_name', 'LIKE', '%' . $search . '%')
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $search . '%']);
                })
                ->orWhere('signature_device_type', 'LIKE', '%' . $search . '%')
                ->orWhere('signature_ip', 'LIKE', '%' . $search . '%')
                ->orWhere('checkoutable_type', 'LIKE', '%' . $search . '%');
            });
        }

        $total = $query->count();
        $results = $query->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $rows = [];
        foreach ($results as $sig) {
            $userName = 'N/A';
            $itemName = 'N/A';
            $itemType = 'N/A';
            $locationDisplay = '';

            if ($sig->assignedTo) {
                $userName = trim(($sig->assignedTo->first_name ?? '') . ' ' . ($sig->assignedTo->last_name ?? ''));
                if (empty($userName)) $userName = 'N/A';
            }

            if ($sig->checkoutable) {
                $itemName = $sig->checkoutable->name ?? ($sig->checkoutable->asset_tag ?? 'N/A');
                $itemType = class_basename(get_class($sig->checkoutable));
            }

            if ($sig->signature_latitude && $sig->signature_longitude) {
                $locationDisplay = '<a href="https://www.google.com/maps?q=' . $sig->signature_latitude . ',' . $sig->signature_longitude . '" target="_blank" title="Ver no mapa"><i class="fas fa-map-marker-alt text-green"></i> ' . round($sig->signature_latitude, 4) . ', ' . round($sig->signature_longitude, 4) . '</a>';
            }

            $actions = '<a href="' . route('reports.eula-signatures.detail', $sig->id) . '" class="btn btn-sm btn-default" title="Detalhes"><i class="fas fa-eye"></i></a>';
            $actions .= ' <a href="' . route('reports.eula-signatures.pdf', $sig->id) . '" class="btn btn-sm btn-default" title="PDF"><i class="fas fa-file-pdf"></i></a>';

            $rows[] = [
                'id' => $sig->id,
                'assigned_to' => $userName,
                'checkoutable' => $itemName,
                'checkoutable_type' => $itemType,
                'accepted_at' => $sig->accepted_at ? $sig->accepted_at->format('Y-m-d H:i:s') : null,
                'signature_device_type' => $sig->signature_device_type ?? 'N/A',
                'location_display' => $locationDisplay,
                'signature_ip' => $sig->signature_ip ?? '',
                'created_at' => $sig->created_at ? $sig->created_at->format('Y-m-d H:i:s') : null,
                'signature_image' => $sig->signature_filename
                    ? route('reports.eula-signatures.signature-image', $sig->signature_filename)
                    : null,
                'actions' => $actions,
            ];
        }

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }

    /**
     * API endpoint for EULA signatures statistics
     * Returns JSON stats object for dashboard cards
     */
    public function getEulaSignaturesApiStats(Request $request): JsonResponse
    {
        $this->authorize('reports.view');

        $search = $request->input('search', '');

        $baseQuery = CheckoutAcceptance::completed();

        if (!empty($search)) {
            $search = trim(strip_tags($search));
            $baseQuery->whereHas('assignedTo', function ($query) use ($search) {
                $query->where('first_name', 'LIKE', '%' . $search . '%')
                    ->orWhere('last_name', 'LIKE', '%' . $search . '%')
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $search . '%']);
            });
        }

        $cacheKey = 'eula_api_stats_' . md5($search);
        $stats = Cache::remember($cacheKey, 300, function () use ($baseQuery) {
            $total = $baseQuery->count();

            $last7Days = (clone $baseQuery)
                ->where('accepted_at', '>=', Carbon::now()->subDays(7))
                ->count();

            $desktopCount = (clone $baseQuery)
                ->where('signature_device_type', 'desktop')
                ->count();

            $mobileCount = (clone $baseQuery)
                ->where('signature_device_type', 'mobile')
                ->count();

            $withGeolocation = (clone $baseQuery)
                ->whereNotNull('signature_latitude')
                ->whereNotNull('signature_longitude')
                ->count();

            return [
                'total' => $total,
                'last_7_days' => $last7Days,
                'desktop_count' => $desktopCount,
                'mobile_count' => $mobileCount,
                'with_geolocation' => $withGeolocation,
                'without_geolocation' => $total - $withGeolocation,
            ];
        });

        return response()->json($stats);
    }
}
