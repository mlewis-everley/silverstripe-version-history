<?php

namespace jonom\SilverStripe\VersionHistory;

use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;

class VersionHistoryController extends Controller
{

    private static $url_handlers = array(
        '$Action/$Model/$ID/$VersionID/$OtherVersionID' => 'handleAction'
    );

    private static $allowed_actions = array(
        'compare'
    );

    /**
     * Return output suitable for an ajax request.
     */
    public function compare()
    {
        $request = $this->getRequest();
        $id = (int) $request->param('ID');
        $model = str_replace('-', '\\', $request->param('Model'));
        $versionID = $request->param('VersionID');
        $otherVersionID = $request->param('OtherVersionID');

        if (!$id) {
            $this->httpError(400, 'No ID specified');
            return false;
        }

        $record = $model::get()->byID($id);

        if (!$record) {
            $this->httpError(404);
            return false;
        }

        if (!$record->canView()) {
            return Security::permissionFailure($this);
        }

        return $record->VersionComparisonSummary($versionID, $otherVersionID);
    }
}
