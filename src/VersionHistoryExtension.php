<?php

namespace jonom\SilverStripe\VersionHistory;

use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldList;
use SilverStripe\Control\Director;
use SilverStripe\ORM\DataExtension;
use SilverStripe\View\Requirements;
use SilverStripe\View\Parsers\Diff;
use SilverStripe\View\ViewableData;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Control\Controller;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\HTMLReadonlyField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;

class VersionHistoryExtension extends DataExtension
{

    public function updateCMSFields(FieldList $fields)
    {
        $vFields = $this->owner->getVersionsFormFields();

        // Only add history field if history exists
        if ($this->owner->ID && $vFields) {
            // URL for ajax request
            $urlBase = Controller::join_links(
                Director::absoluteBaseURL(),
                'cms-version-history',
                'compare',
                str_replace('\\', '-', $this->owner->ClassName),
                $this->owner->ID
            );
            
            $fields->findOrMakeTab('Root.VersionHistory', 'History');
            $fields->addFieldToTab(
                'Root.VersionHistory',
                LiteralField::create(
                    'VersionsHistoryTab',
                    $this->owner->renderWith(
                        "jonom\\SilverStripe\\VersionHistory\\VersionHistoryTab",
                        $vars = [
                            "URL" => $urlBase,
                            "Versions" => $vFields,
                            "Summary" => $this->owner->VersionComparisonSummary()
                        ]
                    )
                )
            );
        }
    }

    /**
     * Return an XML string description of a field value or related has_one record.
     *
     * @param Versioned_Version $record
     * @param array $fieldInfo
     * @return string
     */
    public function getVersionFieldValue($record, $fieldInfo)
    {
        if ($fieldInfo['Type'] == 'HasOne' && method_exists($record, $fieldInfo['Name'])) {
            $hasOne = $record->{$fieldInfo['Name']}();
            return Convert::RAW2XML($hasOne->getTitle());
        } else {
            $dbField = $record->dbObject($fieldInfo['Name']);

            if (is_object($dbField)) {
                if (method_exists($dbField, 'Nice')) {
                    return $dbField->Nice();
                }
                return Convert::RAW2XML($dbField->Value);
            }
        }
    }

    /**
     * Display a pre-rendered read only set of fields summarising a specific version.
     * Includes a comparison with the previous version or arbitrary version if specified.
     * If no version ID is specified the latest version is used.
     *
     * @param int $versionID      (default: null)
     * @param int $otherVersionID (default: null)
     */
    public function VersionComparisonSummary($versionID = null, $otherVersionID = null)
    {
        $toRecord = null;
        $classname = $this->owner->ClassName;

        if ($versionID && $otherVersionID) {
            // Compare two specified versions
            if ($versionID > $otherVersionID) {
                $toVersion = $versionID;
                $fromVersion = $otherVersionID;
            } else {
                $toVersion = $otherVersionID;
                $fromVersion = $versionID;
            }
            $fromRecord = Versioned::get_version(
                $classname,
                $this->owner->ID,
                $fromVersion
            );
            $toRecord = Versioned::get_version(
                $classname,
                $this->owner->ID,
                $toVersion
            );
        } else {
            // Compare specified version with previous. Fallback to latest version if none specified.
            $filter = '';

            if ($versionID) {
                $filter = "\"Version\" <= '$versionID'";
            }

            $versions = $this->owner->AllVersions($filter, '', 2);

            if ($versions->count() === 0) {
                return false;
            }

            $toRecord = $versions->first();
            $fromRecord = ($versions->count() === 1) ? null : $versions->last();
        }

        if (!$toRecord) {
            return false;
        }

        $fields = FieldList::create();

        // Generate a list of fields and information about them
        $fieldNames = array();

        foreach ($this->owner->config()->db as $fieldName => $fieldType) {
            $fieldNames[$fieldName] = array(
                'FieldName' => $fieldName,
                'Name' => $fieldName,
                'Type' => 'Field',
            );
        }
    
        foreach ($this->owner->config()->has_one as $has1) {
            $fieldNames[$has1] = array(
                'FieldName' => $has1.'ID',
                'Name' => $has1,
                'Type' => 'HasOne',
            );
        }

        unset($fieldNames['Version']);

        // Compare values between records and make them look nice
        foreach ($fieldNames as $fieldName => $fieldInfo) {
            $currFieldName = $fieldInfo['FieldName'];

            if ((isset($fromRecord) && $toRecord->{$currFieldName} !== $fromRecord->{$currFieldName})) {
                $compareValue = Diff::compareHTML(
                    $this->getVersionFieldValue($fromRecord, $fieldInfo),
                    $this->getVersionFieldValue($toRecord, $fieldInfo)
                );
            } else {
                $compareValue = $this->getVersionFieldValue($toRecord, $fieldInfo);
            }

            $field = HTMLReadonlyField::create(
                "VersionHistory$fieldName",
                $this->owner->fieldLabel($fieldName),
                $compareValue
            );

            $field->dontEscape = true;
            $fields->push($field);
        }

        return $fields->forTemplate();
    }

    /**
     * Version select form. Main interface between selecting versions to view
     * and comparing multiple versions.
     *
     * @return FieldList
     */
    public function getVersionsFormFields()
    {
        $versions = $this->owner->AllVersions();

        if (!$versions->count()) {
            return false;
        }

        $versions->first()->Active = true;

        $vd = new ViewableData();

        $versionsHtml = $vd->customise(array(
            'Versions' => $versions,
        ))->renderWith('jonom\\SilverStripe\\VersionHistory\\VersionHistory_versions');

        $fields = FieldList::create(
            CheckboxField::create(
                'CompareMode',
                _t('CMSPageHistoryController.COMPAREMODE', 'Compare mode (select two)')
            ),
            LiteralField::create('VersionsHtml', $versionsHtml)
        );

        return $fields;
    }
}
