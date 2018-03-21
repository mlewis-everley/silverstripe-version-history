<% require css('jonom/silverstripe-version-history: client/dist/css/version-history.css') %>
<% require javascript('jonom/silverstripe-version-history: client/dist/js/version-history.js') %>

<div class="row">
    <div class="cms-content-tools cms-panel cms-panel-layout">
        <div
            id="VersionHistoryMenu"
            class="panel panel--scrollable cms-panel-content"
            data-url-base="{$URL}"
        >
            $Versions
        </div>
    </div>

    <div class="col">
        <div id="VersionComparisonSummary">
            $Summary.RAW
        </div>
    </div>
</div>