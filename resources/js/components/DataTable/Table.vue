<template>
    <fieldset
        ref="tableFieldset"
        :key="`table-${name}`"
        :dusk="`table-${name}`"
        class="min-w-0"
        :class="{ 'opacity-75': isVisiting }"
    >
        <div :class="{ 'sticky top-10 z-20': stickyHeader }">
            <div
                class="flex flex-row flex-wrap sm:flex-nowrap justify-end px-0 py-1 pr-2"
            >
                <div
                    class="order-2 sm:order-3 mx-2 pt-1"
                    v-if="
                        queryBuilderProps.hasFilters && !props.hideFiltersColumn
                    "
                >
                    <slot
                        name="tableFilter"
                        :has-filters="queryBuilderProps.hasFilters"
                        :has-enabled-filters="
                            queryBuilderProps.hasEnabledFilters
                        "
                        :filters="queryBuilderProps.filters"
                        :on-filter-change="changeFilterValue"
                    >
                        <TableFilter
                            v-if="queryBuilderProps.hasFilters"
                            :has-enabled-filters="
                                queryBuilderProps.hasEnabledFilters
                            "
                            :filters="queryBuilderProps.filters"
                            :on-filter-change="changeFilterValue"
                            :cascade-data="props.resourceNeo?.cascadeData || []"
                            :cascading-filter-map="props.resourceNeo?.cascadingFilterMap || {}"
                        />
                    </slot>
                </div>

                <div
                    class="flex flex-row w-full sm:w-auto sm:flex-grow order-1 sm:order-1 mb-2 sm:mb-0 ml-2 sm:mx-2 pt-1"
                >
                    <slot
                        name="tableGlobalSearch"
                        :has-global-search="queryBuilderProps.globalSearch"
                        :label="
                            queryBuilderProps.globalSearch
                                ? queryBuilderProps.globalSearch.label
                                : null
                        "
                        :value="
                            queryBuilderProps.globalSearch
                                ? queryBuilderProps.globalSearch.value
                                : null
                        "
                        :on-change="changeGlobalSearchValue"
                    >
                        <TableGlobalSearch
                            v-if="queryBuilderProps.globalSearch"
                            class="w-full lg:w-1/2"
                            :label="queryBuilderProps.globalSearch.label"
                            :value="queryBuilderProps.globalSearch.value"
                            :on-change="changeGlobalSearchValue"
                        />
                    </slot>
                    <div
                        class="order-4 mx-2 sm:order-6"
                        v-if="props.popupSearch"
                    >
                        <div class="relative">
                            <button
                                type="button"
                                title="Filter and Search"
                                class="w-full border border-gray-200 dark:border-slate-700 bg-gray-400 dark:bg-slate-500 text-opacity rounded-md shadow-sm px-4 py-2 inline-flex justify-center text-sm font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                @click="isModalSearchPopupActive = true"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-white-400"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <slot
                    name="tableReset"
                    can-be-reset="canBeReset"
                    :on-click="resetQuery"
                >
                    <div v-if="canBeReset" class="order-7 sm:order-2 mx-2 pt-1">
                        <TableReset :on-click="resetQuery" />
                    </div>
                </slot>
                <slot name="customButtons" />

                <slot
                    name="tableAddSearchRow"
                    :has-search-inputs="queryBuilderProps.hasSearchInputs"
                    :has-search-inputs-without-value="
                        queryBuilderProps.hasSearchInputsWithoutValue
                    "
                    :search-inputs="queryBuilderProps.searchInputsWithoutGlobal"
                    :on-add="showSearchInput"
                >
                    <TableAddSearchRow
                        v-if="
                            queryBuilderProps.hasSearchInputs &&
                            !props.hideSearchColumn
                        "
                        class="order-2 sm:order-4 mr-2 ml-2 sm:mr-2 pt-1"
                        :search-inputs="
                            queryBuilderProps.searchInputsWithoutGlobal
                        "
                        :has-search-inputs-without-value="
                            queryBuilderProps.hasSearchInputsWithoutValue
                        "
                        :on-add="showSearchInput"
                    />
                </slot>
                <div class="order-4 mx-2 sm:order-5 pt-1" v-if="bulkDelete">
                    <div class="relative">
                        <button
                            type="button"
                            title="Delete Selected"
                            :disabled="!selectedRows.length"
                            class="w-full border border-gray-200 dark:border-slate-700 text-opacity rounded-md shadow-sm px-4 py-2 inline-flex justify-center text-sm font-medium hover:border-gray-600 hover:dark:border-slate-100 border-gray-300"
                            @click="isModalSelectedDeleteActive = true"
                            :class="{
                                'bg-gray-200 dark:bg-slate-700 text-gray-400':
                                    !selectedRows.length,
                                'bg-red-600 dark:bg-red-500 text-gray-100':
                                    selectedRows.length,
                            }"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill=" currentColor"
                                    d="M9,3V4H4V6H5V19A2,2 0 0,0 7,21H17A2,2 0 0,0 19,19V6H20V4H15V3H9M9,8H11V17H9V8M13,8H15V17H13V8Z"
                                ></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div
                    class="order-4 mx-2 sm:order-5 pt-1"
                    v-if="
                        props.resourceNeo.bulkActions &&
                        props.resourceNeo.bulkActions.csvExport
                    "
                >
                    <div class="relative">
                        <button
                            ref="button"
                            type="button"
                            title="Export To csv"
                            :disabled="!selectedRows.length"
                            class="w-full border border-gray-200 dark:border-slate-700 text-opacity rounded-md shadow-sm px-4 py-2 inline-flex justify-center text-sm font-medium hover:border-gray-600 hover:dark:border-slate-100 border-gray-300"
                            @click="exportTableToCSV()"
                            :class="{
                                'bg-gray-200 dark:bg-slate-700 text-gray-400':
                                    !selectedRows.length,
                                'bg-gray-50 dark:bg-gray-50 text-black':
                                    selectedRows.length,
                            }"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill="currentColor"
                                    d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M15.8,20H14L12,16.6L10,20H8.2L11.1,15.5L8.2,11H10L12,14.4L14,11H15.8L12.9,15.5L15.8,20M13,9V3.5L18.5,9H13Z"
                                ></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div
                    class="order-4 mx-2 sm:order-5 pt-1"
                    v-if="
                        props.resourceNeo.servExp &&
                        props.resourceNeo.bulkActions &&
                        props.resourceNeo.bulkActions.csvExport
                    "
                >
                    <div class="relative">
                        <a
                            title="All Data Export"
                            class="w-full border border-gray-200 dark:border-slate-700 text-opacity rounded-md shadow-sm px-4 py-2 inline-flex justify-center text-sm font-medium hover:border-gray-600 hover:dark:border-slate-100 border-gray-300"
                            :href="
                                route(
                                    props.resourceNeo.resourceName + '.export'
                                )
                            "
                            target="_blank"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill="currentColor"
                                    d="M4 3H18C19.11 3 20 3.9 20 5V12.08C18.45 11.82 16.92 12.18 15.68 13H12V17H13.08C12.97 17.68 12.97 18.35 13.08 19H4C2.9 19 2 18.11 2 17V5C2 3.9 2.9 3 4 3M4 7V11H10V7H4M12 7V11H18V7H12M4 13V17H10V13H4M19.44 21V19H15.44V17H19.44V15L22.44 18L19.44 21"
                                ></path>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Custom Bulk Actions -->
                <div
                    v-for="(customAction, index) in props.resourceNeo
                        .bulkActions?.customs"
                    :key="`custom-bulk-action-${index}`"
                    class="order-4 mx-2 sm:order-5 pt-1"
                >
                    <div class="relative">
                        <button
                            type="button"
                            :title="customAction.label"
                            :disabled="!selectedRows.length"
                            class="w-full border border-gray-200 dark:border-slate-700 text-opacity rounded-md shadow-sm px-4 py-2 inline-flex justify-center text-sm font-medium hover:border-gray-600 hover:dark:border-slate-100 border-gray-300"
                            @click="handleCustomBulkAction(customAction)"
                            :class="{
                                'bg-gray-200 dark:bg-slate-700 text-gray-400':
                                    !selectedRows.length,
                                'bg-blue-600 dark:bg-blue-500 text-gray-100':
                                    selectedRows.length,
                            }"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                            >
                                <path
                                    fill="currentColor"
                                    :d="customAction.icon"
                                ></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div
                    class="order-4 mx-2 sm:order-6 pt-1"
                    v-if="props.advanceSort"
                >
                    <div class="relative">
                        <button
                            type="button"
                            title="Advance Sort"
                            class="w-full border border-gray-200 dark:border-slate-700 bg-gray-400 dark:bg-slate-500 text-opacity rounded-md shadow-sm px-4 py-2 inline-flex justify-center text-sm font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            @click="isModalSortPopupActive = true"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5 text-white-400"
                                viewBox="0 0 22 22"
                                fill="currentColor"
                            >
                                <path
                                    d="M9.25,5L12.5,1.75L15.75,5H9.25M15.75,19L12.5,22.25L9.25,19H15.75M8.89,14.3H6L5.28,17H2.91L6,7H9L12.13,17H9.67L8.89,14.3M6.33,12.68H8.56L7.93,10.56L7.67,9.59L7.42,8.63H7.39L7.17,9.6L6.93,10.58L6.33,12.68M13.05,17V15.74L17.8,8.97V8.91H13.5V7H20.73V8.34L16.09,15V15.08H20.8V17H13.05Z"
                                    style="fill: currentcolor"
                                ></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <slot
                    name="tableColumns"
                    :has-columns="queryBuilderProps.hasToggleableColumns"
                    :columns="queryBuilderProps.columns"
                    :has-hidden-columns="queryBuilderProps.hasHiddenColumns"
                    :on-change="changeColumnStatus"
                >
                    <TableColumns
                        v-if="
                            queryBuilderProps.hasToggleableColumns &&
                            !props.hideToggleColumn
                        "
                        class="order-6 mx-2 sm:mr-0 sm:order-7 pt-1"
                        :columns="queryBuilderColumns"
                        :has-hidden-columns="queryBuilderProps.hasHiddenColumns"
                        :on-change="changeColumnStatus"
                    />
                </slot>
            </div>
        </div>
        <slot
            name="tableSearchRows"
            :has-search-rows-with-value="
                queryBuilderProps.hasSearchInputsWithValue
            "
            :search-inputs="queryBuilderProps.searchInputsWithoutGlobal"
            :forced-visible-search-inputs="forcedVisibleSearchInputs"
            :on-change="changeSearchInputValue"
        >
            <TableSearchRows
                v-if="
                    !props.hideSearchColumn &&
                    (queryBuilderProps.hasSearchInputsWithValue ||
                        forcedVisibleSearchInputs.length > 0)
                "
                :search-inputs="queryBuilderProps.searchInputsWithoutGlobal"
                :forced-visible-search-inputs="forcedVisibleSearchInputs"
                :on-change="changeSearchInputValue"
                :on-remove="disableSearchInput"
            />
        </slot>
        <div v-if="props.resourceNeo.cellDetail" class="px-2 flex flex-row">
            <div class="mr-1" v-show="true">
                <button
                    type="button"
                    @click="goto"
                    class="w-full border border-gray-200 dark:border-slate-700 rounded-md shadow-sm px-2 py-2 inline-flex justify-center text-sm font-medium hover:border-gray-600 hover:dark:border-slate-100 border-gray-300 bg-gray-200 dark:bg-slate-700 text-gray-400"
                    title="Go To"
                >
                    <span>Go To</span>
                </button>
            </div>
            <input
                type="text"
                class="w-24 h-10 block px-3 py-1 rounded focus:border-transparent focus:ring-0 text-sm border border-gray-200 dark:border-slate-700 bg-gray-100 dark:bg-slate-600 text-opacity"
                v-model="detailCellRef"
                v-show="true"
                id="dcellselectid"
                @keydown.enter.prevent="goto"
            />
            <input
                type="text"
                ref="dcellselect"
                class="flex-1 h-10 block px-3 py-1 rounded focus:border-transparent focus:ring-0 text-sm border border-gray-200 dark:border-slate-700 bg-gray-100 dark:bg-slate-600 text-opacity"
                v-model="detailCell"
                readonly
                @keydown.ctrl.c="controlCopy()"
                @keydown.enter.prevent="entertoEdit"
                @keydown.up="gotoup"
                @keydown.down="gotodown"
                @keydown.left="gotoleft"
                @keydown.right="gotoright"
            />
            <div class="ml-1" v-show="true">
                <button
                    type="button"
                    @click="hx"
                    class="w-full border border-gray-200 dark:border-slate-700 rounded-md shadow-sm px-2 py-2 inline-flex justify-center text-sm font-medium hover:border-gray-600 hover:dark:border-slate-100 border-gray-300 bg-gray-200 dark:bg-slate-700 text-gray-400"
                    title="Hx"
                >
                    <span>Hx</span>
                </button>
            </div>
        </div>

        <slot name="tableWrapper" :meta="resourceMeta">
            <TableWrapper
                :class="{ 'mt-1': !hasOnlyData }"
                :stickyHeader="stickyHeader"
                :actionExpand="props.resourceNeo.actionExpand"
            >
                <slot name="table">
                    <table
                        id="datatable"
                        class="border-t border-gray-500 rounded"
                        v-columns-resizable
                    >
                        <thead>
                            <slot
                                name="head"
                                :show="show"
                                :sort-by="sortBy"
                                :header="header"
                            >
                                <tr>
                                    <th
                                        v-if="!props.resourceNeo.DisCheckboxes"
                                        class="border-r border-gray-300 dark:border-slate-700"
                                        :class="{
                                            ['!' +
                                            headerColor +
                                            ' dark:!' +
                                            headerColor +
                                            ' text-white ']: headerColor,
                                        }"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="selectAll"
                                            @change="selectAllItems"
                                        />
                                    </th>
                                    <HeaderCell
                                        v-for="column in queryBuilderProps.columns"
                                        :colHeader="colHeader"
                                        :key="`table-${name}-header-${column.key}`"
                                        :cell="header(column.key)"
                                        :editIcon="column.extra.editable"
                                        @columnToggle="columnToggle($event)"
                                        :headerColor="headerColor"
                                    />
                                </tr>
                            </slot>
                        </thead>

                        <tbody :key="tableDataKey">
                            <slot name="body" :show="show">
                                <tr
                                    v-for="(item, key) in resourceData"
                                    :key="`tr-${item.id}`"
                                    class="border-b border-t border-gray-300 dark:border-slate-700"
                                    :class="{
                                        'bg-gray-50': striped && key % 2,
                                        'lg:!bg-gray-300 lg:dark:!bg-slate-400/70':
                                            selectedRow == `${item.id}`,
                                        '!bg-gray-400': selectedRows.includes(
                                            item.id
                                        ),
                                    }"
                                >
                                    <td
                                        v-if="!props.resourceNeo.DisCheckboxes"
                                        :data-id="item.id"
                                        class="border-r border-gray-300 dark:border-slate-700"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="
                                                selectedRows.includes(item.id)
                                            "
                                            @click.prevent="selectItem(item)"
                                        />
                                    </td>
                                    <td
                                        v-for="column in queryBuilderProps.columns"
                                        :key="`td-${column.key}-${item.id}`"
                                        v-show="show(column.key)"
                                        class="text-sm p-0 !bg-opacity-75 border-r border-gray-300 dark:border-slate-700"
                                        :dataid="item.id"
                                        :dataval="item[column.key]"
                                        :datakey="column.key"
                                        @dblclick.prevent="showpopper()"
                                        @click="
                                            detailCellShow(
                                                item.id,
                                                column.key,
                                                column.extra.colKey
                                            );
                                            focustoDisplayCell();
                                        "
                                        :class="{
                                            'bg-slate-300 dark:bg-gray-700 ':
                                                column.extra.hidden &&
                                                !column.extra.bg,
                                            [column.extra.bg +
                                            ' dark:' +
                                            column.extra.bg +
                                            ' text-black ']: column.extra.bg,
                                            '!bg-stone-400 dark:!bg-stone-400 !border-2 !border-purple-800':
                                                selectedCell ===
                                                `${item.id}::${column.key}`,
                                        }"
                                        :colKey="column.extra.colKey"
                                        :id="`${column.extra.colKey}${item.id}`"
                                        :dispval="column.extra.dispval"
                                        v-bind:style="{
                                            'max-width': column.extra.width,
                                            'min-width': column.extra.width,
                                        }"
                                    >
                                        <div
                                            class="max-h-16 p-1 break-words relative"
                                            :class="{
                                                'overflow-y-hidden overflow-x-hidden':
                                                    column.key !== 'actions',
                                                ['text-' + column.extra.align]:
                                                    column.extra.align,
                                            }"
                                        >
                                            <slot
                                                :name="`cell(${column.key})`"
                                                :item="item"
                                                :skey="key"
                                            >
                                                {{ column.extra.type === 'datepicker' || column.extra.type === 'datePicker' ? formatDisplayDate(item[column.key]) : item[column.key] }}
                                            </slot>
                                        </div>
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        resourceNeo.showall &&
                                        resource.per_page == 10000
                                    "
                                >
                                    <td class="font-bold text-right">Total</td>
                                    <td
                                        class="p-1 font-bold text-right"
                                        v-for="column in queryBuilderProps.columns"
                                        v-show="show(column.key)"
                                    >
                                        {{ calTotal(column.key) }}
                                    </td>
                                </tr>
                            </slot>
                        </tbody>
                    </table>
                </slot>
            </TableWrapper>
        </slot>
        <slot
            name="pagination"
            :on-click="visit"
            :has-data="hasData"
            :meta="resourceMeta"
            :per-page-options="queryBuilderProps.perPageOptions"
            :on-per-page-change="onPerPageChange"
        >
            <Pagination
                :show-all="props.resourceNeo.showall"
                :on-click="visit"
                :has-data="hasData"
                :meta="resourceMeta"
                :selectedRows="selectedRows"
                :per-page-options="queryBuilderProps.perPageOptions"
                :on-per-page-change="onPerPageChange"
            />
        </slot>
    </fieldset>
    <CardBoxModal
        v-model="isModalSearchPopupActive"
        buttonLabel="Filter"
        title="Filter and Search"
        button="sucess"
        has-cancel
        :fullWidth="true"
    >
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-1">
            <TableWideSearch
                :filters="queryBuilderProps.searchInputsWithoutGlobal"
                :columns="queryBuilderProps.columns"
                v-model="searchFields"
                @formSubmit="doPopupSearch"
            />
        </div>

        <template #footer>
            <BaseButtons>
                <BaseButton
                    label="Filter"
                    color="success"
                    @click="doPopupSearch"
                />
                <BaseButton
                    label="Reset"
                    color="warning"
                    outline
                    @click="resetSearchField"
                />
                <BaseButton
                    label="Cancel"
                    color="warning"
                    outline
                    @click="isModalSearchPopupActive = false"
                />
            </BaseButtons>
        </template>
    </CardBoxModal>

    <CardBoxModal
        v-model="isModalSortPopupActive"
        buttonLabel="Sort"
        title="Advance Sort"
        button="sucess"
        has-cancel
    >
        <div class="lg:max-h-96 overflow-y-auto">
            <AdvanceSort
                v-for="(selm, index) in advSortFields"
                :key="`sortkey${index}`"
                :index="index"
                :column="selm"
                @delrow="deleteSortRow"
                @sortorder="sortOrder"
                v-model="advSortFields[index]"
                :advSortFields="advSortFields"
                @sortfield="sortField"
                :allcolumns="queryBuilderProps.columns"
            />
        </div>
        <BaseButton
            label="Add another sort column"
            color="success"
            @click="addSortFields"
        />
        <template #footer>
            <BaseButtons>
                <BaseButton label="Sort" color="success" @click="doPopupSort" />
                <BaseButton
                    label="Reset"
                    color="warning"
                    outline
                    @click="resetSortField"
                />
                <BaseButton
                    label="Cancel"
                    color="warning"
                    outline
                    @click="isModalSortPopupActive = false"
                />
            </BaseButtons>
        </template>
    </CardBoxModal>

    <CardBoxModal
        v-model="isModalSelectedDeleteActive"
        buttonLabel="Confirm"
        title="Please confirm"
        button="danger"
        has-cancel
        @confirm="deleteSelected"
    >
        <p>
            Are you sure to Delete Selected Item<span class="text-green-400"
                >({{ selectedRows.length }})</span
            >?
        </p>
    </CardBoxModal>

    <CardBoxModal
        :infoText="infotext"
        v-model="isEditPopupActive"
        buttonLabel="Update"
        :title="editPopupTitile"
        button="info"
        has-cancel
        @confirm="popupEditFunction"
        @cancel="gotowithotmessage"
    >
        <input
            v-if="editPopupFieldType == 'input' && !editPopupFieldNumberOnly"
            v-model="editPopupModel"
            @keydown.enter.prevent="
                isEditPopupActive = false;
                popupEditFunction();
            "
            ref="poperinput"
            class="px-3 py-1 max-w-full focus:ring focus:outline-none border-gray-700 rounded w-full dark:placeholder-gray-400 h-10 border bg-white dark:bg-slate-800"
        />

        <input
            v-if="editPopupFieldType == 'input' && editPopupFieldNumberOnly"
            v-model="editPopupModel"
            type="number"
            @keydown.enter.prevent="
                isEditPopupActive = false;
                popupEditFunction();
            "
            ref="poperinput"
            class="px-3 py-1 max-w-full focus:ring focus:outline-none border-gray-700 rounded w-full dark:placeholder-gray-400 h-10 border bg-white dark:bg-slate-800"
        />

        <textarea
            v-if="editPopupFieldType == 'textarea'"
            v-model="editPopupModel"
            @keydown="inputHandlerTextarea"
            ref="poperinput"
            class="px-3 py-1 max-w-full focus:ring focus:outline-none border-gray-700 rounded w-full dark:placeholder-gray-400 h-24 border bg-white dark:bg-slate-800"
        ></textarea>
        <div v-if="editPopupFieldType == 'textarea'">
            Press SHIFT+ENTER to enter a new line (line break) because pressing
            ENTER button alone will SAVE existing data (and not create line
            break).
        </div>
        <CheckboxMulti
            v-if="editPopupFieldType == 'checkbox'"
            v-model="editPopupModel"
            :options="editPopupModelOptions"
        />

        <FormControl
            v-if="editPopupFieldType == 'select'"
            v-model="editPopupModel"
            :options="editPopupModelOptions"
            ref="poperselect"
            @keydown.enter.prevent="
                isEditPopupActive = false;
                popupEditFunction();
            "
        />
        <VueDatePicker
            input-class-name="text-gray-500 dark:text-gray-100 shadow-sm text-sm bg-gray-100 dark:bg-slate-600"
            :month-change-on-scroll="false"
            :range="false"
            :enable-time-picker="false"
            arrow-navigation
            format="dd-MM-yyyy"
            model-type="yyyy-MM-dd"
            auto-apply
            v-if="editPopupFieldType === 'datePicker'"
            :model-value="editPopupModel"
            @update:model-value="setFilterValue"
            uid="poperinput"
        >
        </VueDatePicker>

        <div
            v-if="
                editPopupFreeTextFieldType &&
                (editPopupFieldType == 'select' ||
                    editPopupFieldType == 'input' ||
                    editPopupFieldType == 'checkbox' ||
                    editPopupFieldType === 'datePicker')
            "
        >
            <div v-if="editFreeTextEnableModel" class="text-orange-600 text-md">
                Non-free text field(s) disabled. To re-enabled, please uncheck
                "free text" input to <b>RESET</b> input
                {{ editPopupFreeTextFieldType }}
            </div>
            <br /><br />
            <textarea
                v-model="editFreeTextPopupModel"
                @keydown="inputHandlerTextarea"
                class="px-3 py-1 max-w-full focus:ring focus:outline-none border-gray-700 rounded w-full dark:placeholder-gray-400 h-24 border"
                :class="{
                    'bg-white dark:bg-slate-800': editFreeTextEnableModel,
                    'bg-gray-100 dark:bg-gray-300': !editFreeTextEnableModel,
                }"
                :readonly="!editFreeTextEnableModel"
                v-if="editPopupFreeTextFieldType == 'textarea'"
            ></textarea>
            <input
                v-if="editPopupFreeTextFieldType == 'input'"
                v-model="editFreeTextPopupModel"
                @keydown.enter.prevent="
                    isEditPopupActive = false;
                    popupEditFunction();
                "
                class="px-3 py-1 max-w-full focus:ring focus:outline-none border-gray-700 rounded w-full dark:placeholder-gray-400 h-10 border"
                :class="{
                    'bg-white dark:bg-slate-800': editFreeTextEnableModel,
                    'bg-gray-100 dark:bg-gray-300': !editFreeTextEnableModel,
                }"
                :readonly="!editFreeTextEnableModel"
            />
            <div
                v-if="
                    editPopupFreeTextFieldType &&
                    editFreeTextPopupModel &&
                    ((editPopupFreeTextFieldType == 'input' &&
                        editFreeTextPopupModel.length >= 255) ||
                        (editPopupFreeTextFieldType == 'textarea' &&
                            editFreeTextPopupModel.length >= 65000))
                "
                class="text-red-600"
            >
                Length Exceed!!
            </div>
            <input
                v-model="editFreeTextEnableModel"
                type="checkbox"
                @change="freeTextCheckHandler"
                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
            />
            Check to edit input as free text instead
        </div>

        <!-- <div v-if="infotext">{{ infotext }}</div> -->
        <div
            v-if="
                editPopupModel &&
                ((editPopupFieldType == 'input' &&
                    editPopupModel.length >= 255) ||
                    (editPopupFieldType == 'textarea' &&
                        editPopupModel.length >= 65000))
            "
            class="text-red-600"
        >
            Length Exceed!!
        </div>
    </CardBoxModal>
    <CardBoxModal
        v-model="isModalHxActive"
        title="Update History"
        has-cancel
        @confirm="isModalHxActive = false"
        full-width
        :hasFooter="!1"
        @cancel="gotowithotmessage"
    >
        <div class="lg:max-h-96 overflow-y-auto">
            <div class="text-center" v-if="isModalHxActiveLoader">
                <div role="status">
                    <BaseIcon :path="mdiReload" class="animate-spin" />
                    <span class="sr-only">Loading...</span>
                </div>
            </div>
            <div v-if="historydata.length == 0 && !isModalHxActiveLoader">
                No History Found
            </div>
            <CardBox
                class="mb-1 mr-1 p-2 last:mb-0 bg-white dark:!border-solid"
                :hasTable="!0"
                is-hoverable
                v-for="hist in historydata"
                :key="`histkey${hist.id}`"
            >
                <div class="flex flex-row justify-between">
                    <div>{{ hist.user.name }}</div>
                    <div>{{ formatedDate(hist.created_at) }}</div>
                </div>
                <p><b>Replaced</b>: {{ hist.data_val }}</p>
            </CardBox>
        </div>
    </CardBoxModal>
    <CardBoxModal
        v-model="isModelUncheckActive"
        buttonLabel="Delete"
        title="Please confirm"
        button="danger"
        has-cancel
        @confirm="uncheckConfirm"
        @cancel="uncheckCancel"
    >
        <p>
            Are you sure you want to DELETE all current contents and revert to
            non-free-text field(s)? Lost contents cannot be recovered.
        </p>
    </CardBoxModal>
    <CardBoxModal
        v-model="isModelViewableActive"
        buttonLabel="Copy to Clipboard"
        title="View Data"
        has-cancel
        @confirm="copyVieabletoClipboard"
    >
        <textarea
            v-model="editPopupModel"
            class="px-3 py-1 max-w-full focus:ring focus:outline-none border-gray-700 rounded w-full dark:placeholder-gray-400 h-24 border bg-white dark:bg-slate-800 min-h-[200px]"
            readonly="true"
        ></textarea>
    </CardBoxModal>
    <div style="width: 0px !important; height: 0px !important">
        <input
            id="dummyinput"
            style="
                width: 0px !important;
                height: 0px !important;
                border: 0px !important;
                padding: 0px !important;
            "
        />
    </div>
</template>

<script setup>
import Pagination from "./Pagination.vue";
import HeaderCell from "./HeaderCell.vue";
import TableAddSearchRow from "./TableAddSearchRow.vue";
import TableColumns from "./TableColumns.vue";
import TableFilter from "./TableFilter.vue";
import TableWideSearch from "./TableWideSearch.vue";
import AdvanceSort from "./AdvanceSort.vue";

import TableGlobalSearch from "./TableGlobalSearch.vue";
import TableSearchRows from "./TableSearchRows.vue";
import TableReset from "./TableReset.vue";
import TableWrapper from "./TableWrapper.vue";
import BaseButton from "@/components/BaseButton.vue";
import BaseButtons from "@/components/BaseButtons.vue";
import {
    computed,
    onMounted,
    ref,
    watch,
    onUnmounted,
    getCurrentInstance,
    nextTick,
    Transition,
} from "vue";
import qs from "qs";
import clone from "lodash-es/clone";
import filter from "lodash-es/filter";
import findKey from "lodash-es/findKey";
import forEach from "lodash-es/forEach";
import isEqual from "lodash-es/isEqual";
import map from "lodash-es/map";
import pickBy from "lodash-es/pickBy";
import FormControl from "@/components/FormControl.vue";
import CardBoxModal from "@/components/CardBoxModal.vue";
import { router, usePage } from "@inertiajs/vue3";
import { useToast } from "vue-toast-notification";
import "vue-toast-notification/dist/theme-sugar.css";

import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import CheckboxMulti from "../CheckboxMulti.vue";
import { mdiReload } from "@mdi/js";
import BaseIcon from "@/components/BaseIcon.vue";
import CardBox from "@/components/CardBox.vue";
import axios from "axios";
import { formatedDate, formatDisplayDate } from "@/helpers/helpers";
const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");

const props = defineProps({
    inertia: {
        type: Object,
        default: () => {
            return {};
        },
        required: false,
    },
    resourceNeo: {
        type: Object,
        default: () => ({}),
    },
    name: {
        type: String,
        default: "default",
        required: false,
    },

    striped: {
        type: Boolean,
        default: false,
        required: false,
    },

    resizable: {
        type: Boolean,
        default: false,
        required: false,
    },
    stickyHeader: {
        type: Boolean,
        default: false,
        required: false,
    },

    colHeader: {
        type: Boolean,
        default: false,
        required: false,
    },

    headerColor: {
        type: String,
        required: false,
    },

    multipleSort: {
        type: Boolean,
        default: false,
        required: false,
    },
    advanceSort: {
        type: Boolean,
        default: false,
        required: false,
    },
    hideToggleColumn: {
        type: Boolean,
        default: false,
        required: false,
    },

    hideSearchColumn: {
        type: Boolean,
        default: false,
        required: false,
    },
    hideFiltersColumn: {
        type: Boolean,
        default: false,
        required: false,
    },

    enterKeyToEdit: {
        type: Boolean,
        default: false,
        required: false,
    },

    seleLimit: {
        type: Number,
        default: 0,
        required: false,
    },
    popupSearch: {
        type: Boolean,
        default: false,
        required: false,
    },

    preventOverlappingRequests: {
        type: Boolean,
        default: true,
        required: false,
    },

    inputDebounceMs: {
        type: Number,
        default: 750,
        required: false,
    },

    preserveScroll: {
        type: [Boolean, String],
        default: false,
        required: false,
    },

    resource: {
        type: Object,
        default: () => {
            return {};
        },
        required: false,
    },

    meta: {
        type: Object,
        default: () => {
            return {};
        },
        required: false,
    },

    data: {
        type: Object,
        default: () => {
            return {};
        },
        required: false,
    },
});
const isModalHxActive = ref(false);
const isModalHxActiveLoader = ref(false);
const historydata = ref([]);
const hx = () => {
    const el = document.getElementById(detailCellRef.value.toUpperCase());
    if (el) {
        const did = el.getAttribute("dataid");
        const dkey = el.getAttribute("datakey");
        isModalHxActive.value = true;
        isModalHxActiveLoader.value = true;
        const historyReq = {
            module: props.resourceNeo.resourceName,
            did: did,
            dkey: dkey,
        };
        axios
            .post(route("activitylog.history"), historyReq)
            .then((response) => {
                isModalHxActiveLoader.value = false;
                historydata.value = response.data;
            });
    } else {
        useToast().error("Not Valid Cell refrence found!!");
        return false;
    }
};

const detailCell = ref("");
const detailCellRef = ref("");
const emit = defineEmits(["selectedRows", "selectedCell"]);

const dcellselect = ref(null);
const controlCopy = () => {
    //dcellselect.value.select();
    //document.execCommand("copy");
    copyToClipboard(detailCell.value);
    useToast().info("Copied to clipboard!!");
    dcellselect.value.focus();
};

function copyToClipboard(text) {
    var dummy = document.createElement("textarea");
    document.body.appendChild(dummy);
    dummy.value = text;
    dummy.select();
    document.execCommand("copy");
    document.body.removeChild(dummy);
}

const bulkDelete = computed(() => {
    if (
        props.resourceNeo.bulkActions &&
        props.resourceNeo.bulkActions.bulk_delete
    ) {
        return true;
    }
});

watch(
    message,
    (newVal) => {
        if (newVal) {
            if (msg_type.value == "info") {
                useToast().info(newVal, { duration: 7000 });
            } else if (msg_type.value == "success") {
                useToast().success(newVal, { duration: 7000 });
            } else if (msg_type.value == "danger") {
                useToast().error(newVal, { duration: 7000 });
            } else {
                useToast().warning(newVal, { duration: 7000 });
            }
        }
    },
    { deep: true }
);

const isEditPopupActive = ref(false);
const editPopupTitile = ref("");
const editPopupModel = ref("");
const editFreeTextPopupModel = ref("");
const editFreeTextEnableModel = ref(false);
const editPopupRefId = ref(null);
const editPopupFieldType = ref("input");
const editPopupFieldNumberOnly = ref(false);
const editPopupFreeTextFieldType = ref("input");
const editPopupModelOptions = ref(null);

const infotext = ref("");

const poperinput = ref(null);
const poperselect = ref(null);

function popupEditFunction() {
    if (
        editPopupFieldType.value == "input" &&
        editPopupModel.value &&
        editPopupModel.value.length >= 255
    ) {
        useToast().error("Text Length Excced!!");
        return false;
    }

    if (
        editFreeTextEnableModel.value == true &&
        editPopupFreeTextFieldType.value == "input" &&
        editFreeTextPopupModel.value &&
        editFreeTextPopupModel.value.length >= 255
    ) {
        useToast().error("Text Length Excced!!");
        return false;
    }

    if (
        editFreeTextEnableModel.value == false &&
        editPopupModel.value &&
        editPopupModel.value.id == null &&
        editPopupModel.value.label == ""
    ) {
        editPopupModel.value = null;
    }
    const idkey = selectedCell.value.split("::");
    router.post(
        route(props.resourceNeo.resourceName + ".fieldUpdate", {
            id: idkey[0],
            key: idkey[1],
            ref: selectedCellId.value,
            value:
                editFreeTextEnableModel.value == true
                    ? editFreeTextPopupModel.value
                    : editPopupModel.value && editPopupModel.value.id
                    ? editPopupModel.value.id
                    : editPopupModel.value,
        })
    );
}

const handleCustomBulkAction = (action) => {
    const payload = { ids: selectedRows.value };
    const routeName = action.function;

    if (action.action === "post") {
        if (action.target === "blank") {
            // Create a form to submit in a new tab
            const form = document.createElement("form");
            form.method = "POST";
            form.action = route(routeName);
            form.target = "_blank";

            // Add CSRF token from meta tag
            const csrfToken = document.head.querySelector(
                'meta[name="csrf-token"]'
            )?.content;
            if (csrfToken) {
                const csrfInput = document.createElement("input");
                csrfInput.type = "hidden";
                csrfInput.name = "_token";
                csrfInput.value = csrfToken;
                form.appendChild(csrfInput);
            } else {
                console.warn(
                    "CSRF token not found. The POST request might fail."
                );
            }

            // Add IDs
            selectedRows.value.forEach((id) => {
                const idInput = document.createElement("input");
                idInput.type = "hidden";
                idInput.name = "ids[]";
                idInput.value = id;
                form.appendChild(idInput);
            });

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            // Reset selection and show toast
            useToast().success(`${action.label} initiated successfully!`);
            selectedRows.value = [];
            resetSelect();
        } else {
            // Existing logic for same-tab POST
            router.post(route(routeName), payload, {
                preserveScroll: true,
                resetOnSuccess: false,
                onFinish: () => {
                    selectedRows.value = [];
                    resetSelect();
                },
                onSuccess: () => {
                    useToast().success(
                        `${action.label} initiated successfully!`
                    );
                },
                onError: (errors) => {
                    useToast().error(
                        `Error during ${action.label}: ${Object.values(
                            errors
                        ).join(", ")}`
                    );
                },
            });
        }
    } else if (action.action === "get") {
        const url = route(routeName, { ids: selectedRows.value.join(",") });
        if (action.target === "blank") {
            window.open(url, "_blank");
        } else {
            // For GET requests, we can redirect or trigger a download
            // For simplicity, we'll use window.location.href to trigger a full page load/download
            // If the target is an Inertia page, router.get() would be preferred.
            window.location.href = url;
        }
        useToast().info(`${action.label} request sent.`);
    }
};

const inputHandlerTextarea = (e) => {
    if (e.keyCode === 13 && !e.shiftKey) {
        e.preventDefault();
        isEditPopupActive.value = false;
        popupEditFunction();
    }
};

const selectedCell = ref(null);
const selectedCellId = ref(null);
const selectedRow = ref(null);
function detailCellShow(id, key, colkey = "A") {
    selectedCellId.value = colkey + id;
    selectedCell.value = id + "::" + key;
    selectedRow.value = id;
    const el = document.getElementById((colkey + id).toUpperCase());
    if (el != null) {
        detailCellRef.value = el.getAttribute("id");
        emit("selectedCell", detailCellRef.value);
        detailCell.value =
            el.getAttribute("dataval") != el.innerText
                ? el.innerText
                : el.getAttribute("dataval");

        if (el.getAttribute("dispval") == "dataval") {
            detailCell.value = el.getAttribute("dataval");
        }
    }
}

const focustoDisplayCell = () => {
    if (dcellselect.value) {
        dcellselect.value.focus();
    }
};

const gotoup = (e) => {
    e.preventDefault();
    const el = document.getElementById(detailCellRef.value.toUpperCase());
    if (el) {
        let cIndex = el.cellIndex;
        let rIndex = el.parentElement.rowIndex;
        let nrow = document.getElementById("datatable").rows[rIndex - 1];
        if (nrow) {
            let dcell = nrow.cells[cIndex].getAttribute("id");
            const el2 = document.getElementById(dcell);
            if (el2) {
                gotocell2(el2);
            }
        }
    }
};

const gotodown = (e) => {
    e.preventDefault();
    const el = document.getElementById(detailCellRef.value.toUpperCase());
    if (el) {
        let cIndex = el.cellIndex;
        let rIndex = el.parentElement.rowIndex;
        let nrow = document.getElementById("datatable").rows[rIndex + 1];
        if (nrow) {
            let dcell = nrow.cells[cIndex].getAttribute("id");
            const el2 = document.getElementById(dcell);
            if (el2) {
                gotocell2(el2);
            }
        }
    }
};

const gotoleft = (e) => {
    e.preventDefault();
    const el = document.getElementById(detailCellRef.value.toUpperCase());
    if (el) {
        let cIndex = el.cellIndex;
        if (cIndex > 1) {
            let nel = el.previousSibling;
            while (nel.style && nel.style.display == "none") {
                nel = nel.previousSibling;
            }
            gotocell2(nel);
        }
    }
};

const gotoright = (e) => {
    e.preventDefault();
    const el = document.getElementById(detailCellRef.value.toUpperCase());
    if (el) {
        let cIndex = el.cellIndex;
        if (
            cIndex <
            document.getElementById("datatable").rows[0].cells.length - 1
        ) {
            let nel = el.nextSibling;
            while (nel.style && nel.style.display == "none") {
                nel = nel.nextSibling;
            }
            gotocell2(nel);
        }
    }
};

const goto = () => {
    const el = document.getElementById(detailCellRef.value.toUpperCase());
    gotocell(el);
};

const gotowithotmessage = () => {
    const el = document.getElementById(detailCellRef.value.toUpperCase());
    if (el) {
        gotocell(el);
    }
};

const gotocell = (el) => {
    if (el) {
        if (el.style && el.style.display == "none") {
            useToast().error(
                "Cell Reference found but hidden!! Expand Col to see."
            );
        }
        el.scrollIntoView({ block: "nearest", inline: "nearest" });
        let rect = el.getBoundingClientRect();
        let elemTop = rect.top;
        if (elemTop < 175) {
            let rIndex = el.parentElement.rowIndex;
            let nrow = document.getElementById("datatable").rows[rIndex - 1];
            nrow.scrollIntoView({ block: "nearest", inline: "nearest" });
        }

        selectedCell.value =
            el.getAttribute("dataid") + "::" + el.getAttribute("datakey");
        detailCellShow(
            el.getAttribute("dataid"),
            el.getAttribute("datakey"),
            el.getAttribute("colKey")
        );
        focustoDisplayCell();
    } else {
        useToast().error(
            "Cell Ref NOT found in this Page. Pls Use Filter & Search instead!!"
        );
        return false;
    }
};

const gotocell2 = (el) => {
    if (el) {
        if (el.style && el.style.display == "none") {
            useToast().error(
                "Cell Reference found but hidden!! Expand Col to see."
            );
        }
        el.scrollIntoView({ block: "nearest", inline: "nearest" });
        let rect = el.getBoundingClientRect();
        let elemTop = rect.top;
        if (elemTop < 175) {
            let rIndex = el.parentElement.rowIndex;
            let nrow = document.getElementById("datatable").rows[rIndex - 1];
            nrow.scrollIntoView({ block: "nearest", inline: "nearest" });
        }

        selectedCell.value =
            el.getAttribute("dataid") + "::" + el.getAttribute("datakey");
        detailCellShow(
            el.getAttribute("dataid"),
            el.getAttribute("datakey"),
            el.getAttribute("colKey")
        );
    } else {
        useToast().error(
            "Cell Ref NOT found in this Page. Pls Use Filter & Search instead!!"
        );
        return false;
    }
};

function entertoEdit() {
    if (props.enterKeyToEdit && selectedCell.value) {
        showpopper();
    }
}
const copyVieabletoClipboard = () => {
    copyToClipboard(editPopupModel.value);
    useToast().info("Contents copied to clipboard!!");
};

const isModelViewableActive = ref(false);
function showpopper() {
    const idkey = selectedCell.value.split("::");
    const id = idkey[0];
    const el = document.getElementById(selectedCellId.value);
    if (el != null) {
        const columnIndex = findDataKey("columns", idkey[1]);
        if (!queryBuilderData.value.columns[columnIndex].extra.editable) {
            if (queryBuilderData.value.columns[columnIndex].extra.viewable) {
                isModelViewableActive.value = true;
                editPopupModel.value = el.getAttribute("dataval");
            } else if (
                queryBuilderData.value.columns[columnIndex].extra.dblClEventTrOn
            ) {
                var doubleClickEvent = document.createEvent("MouseEvents");
                doubleClickEvent.initEvent("dblclick", true, true);
                document
                    .getElementById(
                        queryBuilderData.value.columns[columnIndex].extra
                            .dblClEventTrOn + id
                    )
                    .dispatchEvent(doubleClickEvent);
            } else if (
                queryBuilderData.value.columns[columnIndex].extra.clEventTrOn
            ) {
                var doubleClickEvent = document.createEvent("MouseEvents");
                doubleClickEvent.initEvent("click", true, true);
                document
                    .getElementById(
                        queryBuilderData.value.columns[columnIndex].extra
                            .clEventTrOn + id
                    )
                    .dispatchEvent(doubleClickEvent);
            } else {
                useToast().error("Not Editable!!");
            }
            return false;
        }

        if (queryBuilderData.value.columns[columnIndex].extra.info) {
            infotext.value =
                queryBuilderData.value.columns[columnIndex].extra.info;
        } else {
            infotext.value = "";
        }
        editPopupTitile.value =
            "Update " + queryBuilderData.value.columns[columnIndex].label;
        editPopupModel.value = el.getAttribute("dataval");
        editFreeTextPopupModel.value = el.getAttribute("dataval");
        editFreeTextEnableModel.value = false;

        editPopupFieldNumberOnly.value = queryBuilderData.value.columns[
            columnIndex
        ].extra.onlyNumber
            ? true
            : false;

        editPopupFieldType.value =
            queryBuilderData.value.columns[columnIndex].extra.type;

        editPopupFreeTextFieldType.value =
            queryBuilderData.value.columns[columnIndex].extra.freetext;

        if (
            typeof queryBuilderData.value.columns[columnIndex].extra.type ==
            "undefined"
        ) {
            editPopupFieldType.value = "input";
        } else if (
            queryBuilderData.value.columns[columnIndex].extra.type == "select"
        ) {
            editPopupFieldType.value =
                queryBuilderData.value.columns[columnIndex].extra.type;
            editPopupModelOptions.value = clone(
                queryBuilderData.value.columns[columnIndex].extra.options
            );
            editPopupModelOptions.value.shift();
            //editPopupModelOptions.value[0]['label'] = 'Select ' + editPopupModelOptions.value[0]['label'];
            editPopupModel.value = {
                id: el.getAttribute("dataval"),
                label: el.innerText,
            };
            var indexedArray = [];
            editPopupModelOptions.value.forEach(function (tempoptitem) {
                indexedArray.push(tempoptitem.id);
            });
            if (editFreeTextPopupModel.value) {
                if (indexedArray.includes(editFreeTextPopupModel.value)) {
                    editFreeTextEnableModel.value = false;
                } else {
                    editFreeTextEnableModel.value = true;
                }
            }
        } else if (
            queryBuilderData.value.columns[columnIndex].extra.type == "checkbox"
        ) {
            editPopupFieldType.value =
                queryBuilderData.value.columns[columnIndex].extra.type;
            editPopupModelOptions.value =
                queryBuilderData.value.columns[columnIndex].extra.options;
            let temparray = el.getAttribute("dataval")
                ? el.getAttribute("dataval").split(/\r?\n/)
                : [];

            var indexedArray = [];
            editPopupModelOptions.value.forEach(function (tempoptitem) {
                indexedArray.push(tempoptitem.id);
            });
            let temparray2 = [];
            temparray.forEach(function (tempoptitem) {
                if (indexedArray.includes(tempoptitem)) {
                    temparray2.push(tempoptitem);
                }
            });
            editPopupModel.value = temparray2;

            editFreeTextEnableModel.value = false;
            temparray.forEach(function (tempoptitem, index, arr) {
                if (!indexedArray.includes(tempoptitem)) {
                    editFreeTextEnableModel.value = true;
                    arr.length = index + 1;
                }
            });
        }

        isEditPopupActive.value = true;
        if (
            typeof queryBuilderData.value.columns[columnIndex].extra.type ==
                "undefined" ||
            queryBuilderData.value.columns[columnIndex].extra.type == "input" ||
            queryBuilderData.value.columns[columnIndex].extra.type == "textarea"
        ) {
            setTimeout(function () {
                poperinput.value.focus();
            }, 100);
        }
        if (
            queryBuilderData.value.columns[columnIndex].extra.type ==
            "datePicker"
        ) {
            setTimeout(function () {
                document.getElementById("dp-input-poperinput").focus();
            }, 100);
        }

        if (
            queryBuilderData.value.columns[columnIndex].extra.type == "select"
        ) {
            setTimeout(function () {
                poperselect.value.selectFocus();
            }, 100);
        }
    }
}
const isModelUncheckActive = ref(false);
const freeTextCheckHandler = () => {
    if (!editFreeTextEnableModel.value) {
        isModelUncheckActive.value = true;
    }
    /*
  else {
    editFreeTextEnableModel.value = !editFreeTextEnableModel.value
  }
  
  editFreeTextEnableModel.value = !editFreeTextEnableModel.value
  */
};
const uncheckConfirm = () => {
    editFreeTextEnableModel.value = false;
};
const uncheckCancel = () => {
    editFreeTextEnableModel.value = true;
};

const selectAll = ref(false);
const selectedRows = ref([]);
const selectAllItems = () => {
    if (selectAll.value) {
        selectAll.value = false;
        selectedRows.value = [];
    } else {
        selectAll.value = true;
        if (props.resource.data) {
            selectedRows.value = props.resource.data.map((item) => item.id);
        } else {
            selectedRows.value = props.resource.map((item) => item.id);
        }
    }

    emit("selectedRows", selectedRows.value);
};

const selectItem = (item) => {
    setTimeout(function () {
        if (!selectedRows.value.includes(item.id)) {
            selectedRows.value.push(item.id);
        } else {
            selectedRows.value.splice(selectedRows.value.indexOf(item.id), 1);
        }
        emit("selectedRows", selectedRows.value);
    }, 100);
};

const resetSelect = () => {
    selectAll.value = false;
    selectedRows.value = [];
    emit("selectedRows", selectedRows.value);
};

const isModalSortPopupActive = ref(false);
const advSortFields = ref([{ f: "", o: 1 }]);
const doPopupSort = () => {
    const allsorts = [];
    advSortFields.value.forEach(function (allsort, index) {
        if (allsort.f != "") {
            if (allsort.o == 1) {
                allsorts.push(allsort.f);
            } else {
                allsorts.push(`-${allsort.f}`);
            }
        }
    });
    queryBuilderData.value.sort = allsorts.length ? allsorts.join(",") : "";

    queryBuilderData.value.cursor = null;
    queryBuilderData.value.page = 1;
    isModalSortPopupActive.value = false;
};

const addSortFields = () => {
    advSortFields.value.push({ f: "", o: 1 });
};
const resetSortField = () => {
    advSortFields.value = [{ f: "", o: 1 }];
};
const deleteSortRow = (el) => {
    advSortFields.value.splice(el * 1, 1);
};
const sortOrder = (re) => {
    advSortFields.value[re[0]].o = re[1];
};
const sortField = (re) => {
    advSortFields.value[re[0]].f = re[1];
};

const isModalSearchPopupActive = ref(false);
const searchFields = ref([]);
const resetSearchField = () => {
    queryBuilderProps.value.columns.forEach((element) => {
        if (element.extra.type == "select") {
            searchFields.value[element.key] = element.extra.options[0];
        } else if (element.extra.type == "datePicker") {
            searchFields.value[`${element.key}_start`] = null;
            searchFields.value[`${element.key}_end`] = null;
        } else {
            searchFields.value[element.key] = null;
        }
    });
};
onMounted(() => resetSearchField());

const doPopupSearch = () => {
    const searchFieldsemit = ref([]);
    let tempv;
    Object.entries(queryBuilderProps.value.searchInputsWithoutGlobal).forEach(
        (element) => {
            if (
                queryBuilderColumns.value[
                    findDataKey("columns", element[1].key)
                ].extra.type == "datePicker"
            ) {
                let val = searchFields.value[element[1].key + "_start"];
                if (val != "") {
                    searchFieldsemit.value[element[1].key + "_start"] = val;
                }
                let val2 = searchFields.value[element[1].key + "_end"];
                if (val2 != "") {
                    searchFieldsemit.value[element[1].key + "_end"] = val2;
                }
            } else {
                let val = searchFields.value[element[1].key];
                tempv = val ? val.id ?? val : "";
                if (tempv != "") {
                    searchFieldsemit.value[element[1].key] = tempv;
                }
            }
        }
    );
    isModalSearchPopupActive.value = false;
    clearTimeout(debounceTimeouts["poupsearch"]);
    debounceTimeouts["poupsearch"] = setTimeout(() => {
        if (visitCancelToken.value && props.preventOverlappingRequests) {
            visitCancelToken.value.cancel();
        }
        queryBuilderData.value.searchInputs.forEach(function (key, val) {
            queryBuilderData.value.searchInputs[val].value = null;
        });
        Object.entries(searchFieldsemit.value).forEach(function (ele) {
            let intKey = findDataKey("searchInputs", ele[0]);
            if (intKey) {
                queryBuilderData.value.searchInputs[intKey].value = ele[1];
            }
            let intKey2 = findDataKey("filters", ele[0]);
            if (intKey2) {
                queryBuilderData.value.filters[intKey2].value = ele[1];
            }
        });
        queryBuilderData.value.cursor = null;
        queryBuilderData.value.page = 1;
    }, props.inputDebounceMs);
};
const isModalSelectedDeleteActive = ref(false);
const deleteSelected = () => {
    router.delete(
        route(props.resourceNeo.resourceName + ".bulkDestroy", {
            ids: selectedRows.value,
        }),
        {
            preserveScroll: true,
            resetOnSuccess: false,
            onFinish: () => {
                selectedRows.value = [];
                resetSelect();
            },
        }
    );
};

const app = getCurrentInstance();
const $inertia = app
    ? app.appContext.config.globalProperties.$inertia
    : props.inertia;

const updates = ref(0);

const queryBuilderProps = computed(() => {
    let data = usePage().props.queryBuilderProps
        ? usePage().props.queryBuilderProps[props.name] || {}
        : {};

    data._updates = updates.value;

    return data;
});

const queryBuilderData = ref(queryBuilderProps.value);

const queryBuilderColumns = ref(
    JSON.parse(JSON.stringify(queryBuilderProps.value.columns))
);

const pageName = computed(() => {
    return queryBuilderProps.value.pageName;
});

const forcedVisibleSearchInputs = ref([]);

const tableFieldset = ref(null);

const hasOnlyData = computed(() => {
    if (queryBuilderProps.value.hasToggleableColumns) {
        return false;
    }

    if (queryBuilderProps.value.hasFilters) {
        return false;
    }

    if (queryBuilderProps.value.hasSearchInputs) {
        return false;
    }

    if (queryBuilderProps.value.globalSearch) {
        return false;
    }

    return true;
});

const resourceData = computed(() => {
    if (Object.keys(props.resource).length === 0) {
        return props.data;
    }

    if ("data" in props.resource) {
        return props.resource.data;
    }

    return props.resource;
});

// Computed key to force tbody re-render when data changes
// This fixes the issue where stale cached data is displayed instead of fresh server data
const tableDataKey = computed(() => {
    const data = resourceData.value;
    if (data && data.length > 0) {
        // Create a unique key from first item's ID, last item's ID, and total count
        const firstId = data[0]?.id || 'none';
        const lastId = data[data.length - 1]?.id || 'none';
        return `tbody-${firstId}-${lastId}-${data.length}`;
    }
    return `tbody-empty-${Date.now()}`;
});

const resourceMeta = computed(() => {
    if (Object.keys(props.resource).length === 0) {
        return props.meta;
    }

    if ("links" in props.resource && "meta" in props.resource) {
        if (
            Object.keys(props.resource.links).length === 4 &&
            "next" in props.resource.links &&
            "prev" in props.resource.links
        ) {
            return {
                ...props.resource.meta,
                next_page_url: props.resource.links.next,
                prev_page_url: props.resource.links.prev,
            };
        }
    }

    if ("meta" in props.resource) {
        return props.resource.meta;
    }

    return props.resource;
});

const hasData = computed(() => {
    if (resourceData.value.length > 0) {
        return true;
    }

    if (resourceMeta.value.total > 0) {
        return true;
    }

    return false;
});

//

function disableSearchInput(key) {
    forcedVisibleSearchInputs.value = forcedVisibleSearchInputs.value.filter(
        (search) => search != key
    );

    changeSearchInputValue(key, null);
}

function showSearchInput(key) {
    forcedVisibleSearchInputs.value.push(key);
}

const canBeReset = computed(() => {
    if (forcedVisibleSearchInputs.value.length > 0) {
        return true;
    }

    const queryStringData = qs.parse(location.search.substring(1));

    const page = queryStringData[pageName.value];

    if (page > 1) {
        return true;
    }

    const prefix = props.name === "default" ? "" : props.name + "_";
    let dirty = false;

    forEach(["filter", "columns", "cursor", "sort"], (key) => {
        const value = queryStringData[prefix + key];

        if (key === "sort" && value === queryBuilderProps.value.defaultSort) {
            return;
        }

        if (value !== undefined) {
            dirty = true;
        }
    });

    return dirty;
});

function resetQuery() {
    forcedVisibleSearchInputs.value = [];

    forEach(queryBuilderData.value.filters, (filter, key) => {
        queryBuilderData.value.filters[key].value = null;
    });

    forEach(queryBuilderData.value.searchInputs, (filter, key) => {
        queryBuilderData.value.searchInputs[key].value = null;
    });

    forEach(queryBuilderData.value.columns, (column, key) => {
        queryBuilderData.value.columns[key].hidden = column.can_be_hidden
            ? !queryBuilderProps.value.defaultVisibleToggleableColumns.includes(
                  column.key
              )
            : false;
    });

    queryBuilderData.value.sort = null;
    queryBuilderData.value.cursor = null;
    queryBuilderData.value.page = 1;
}

const debounceTimeouts = {};

function changeSearchInputValue(key, value) {
    clearTimeout(debounceTimeouts[key]);

    debounceTimeouts[key] = setTimeout(() => {
        if (visitCancelToken.value && props.preventOverlappingRequests) {
            visitCancelToken.value.cancel();
        }

        const intKey = findDataKey("searchInputs", key);

        queryBuilderData.value.searchInputs[intKey].value = value;
        queryBuilderData.value.cursor = null;
        queryBuilderData.value.page = 1;
    }, props.inputDebounceMs);
}

function changeGlobalSearchValue(value) {
    changeSearchInputValue("global", value);
}

function changeFilterValue(key, value) {
    const intKey = findDataKey("filters", key);

    queryBuilderData.value.filters[intKey].value = value;
    queryBuilderData.value.cursor = null;
    queryBuilderData.value.page = 1;
}

function onPerPageChange(value) {
    queryBuilderData.value.cursor = null;
    queryBuilderData.value.perPage = value;
    queryBuilderData.value.page = 1;
}

function findDataKey(dataKey, key) {
    return findKey(queryBuilderData.value[dataKey], (value) => {
        return value.key == key;
    });
}

function changeColumnStatus(key, visible) {
    const intKey = findDataKey("columns", key);
    queryBuilderColumns.value[intKey].hidden = !visible;
}

function getFilterForQuery() {
    let filtersWithValue = {};

    forEach(queryBuilderData.value.searchInputs, (searchInput) => {
        if (searchInput.value !== null) {
            filtersWithValue[searchInput.key] = searchInput.value;
        }
    });

    forEach(queryBuilderData.value.filters, (filters) => {
        if (filters.value !== null) {
            filtersWithValue[filters.key] = filters.value;
        }
    });

    return filtersWithValue;
}

function getColumnsForQuery() {
    const columns = queryBuilderData.value.columns;

    let visibleColumns = filter(columns, (column) => {
        return !column.hidden;
    });

    let visibleColumnKeys = map(visibleColumns, (column) => {
        return column.key;
    }).sort();

    if (
        isEqual(
            visibleColumnKeys,
            queryBuilderProps.value.defaultVisibleToggleableColumns
        )
    ) {
        return {};
    }

    return visibleColumnKeys.length ? visibleColumnKeys : ["na"];
}

function dataForNewQueryString() {
    const filterForQuery = getFilterForQuery();
    const columnsForQuery = getColumnsForQuery();

    const queryData = {};

    if (Object.keys(filterForQuery).length > 0) {
        queryData.filter = filterForQuery;
    }

    if (Object.keys(columnsForQuery).length > 0) {
        queryData.columns = columnsForQuery;
    }

    const cursor = queryBuilderData.value.cursor;
    const page = queryBuilderData.value.page;
    const sort = queryBuilderData.value.sort;
    const perPage = queryBuilderData.value.perPage;

    if (cursor) {
        queryData.cursor = cursor;
    }

    if (page > 1) {
        queryData.page = page;
    }

    if (perPage > 1) {
        queryData.perPage = perPage;
    }

    if (sort) {
        queryData.sort = sort;
    }

    return queryData;
}

function generateNewQueryString() {
    const queryStringData = qs.parse(location.search.substring(1));

    const prefix = props.name === "default" ? "" : props.name + "_";

    forEach(["filter", "columns", "cursor", "sort"], (key) => {
        delete queryStringData[prefix + key];
    });

    delete queryStringData[pageName.value];

    forEach(dataForNewQueryString(), (value, key) => {
        if (key === "page") {
            queryStringData[pageName.value] = value;
        } else if (key === "perPage") {
            queryStringData.perPage = value;
        } else {
            queryStringData[prefix + key] = value;
        }
    });

    let query = qs.stringify(queryStringData, {
        filter(prefix, value) {
            if (typeof value === "object" && value !== null) {
                return pickBy(value);
            }

            return value;
        },

        skipNulls: true,
        strictNullHandling: true,
    });

    if (!query || query === pageName.value + "=1") {
        query = "";
    }

    return query;
}

const isVisiting = ref(false);
const visitCancelToken = ref(null);

function visit(url) {
    if (!url) {
        return;
    }

    $inertia.get(
        url,
        {},
        {
            replace: true,
            preserveState: true,
            preserveScroll: props.preserveScroll !== false,
            onBefore() {
                isVisiting.value = true;
            },
            onCancelToken(cancelToken) {
                visitCancelToken.value = cancelToken;
            },
            onFinish() {
                isVisiting.value = false;
            },
            onSuccess() {
                if ("queryBuilderProps" in usePage().props) {
                    queryBuilderData.value.cursor =
                        queryBuilderProps.value.cursor;
                    queryBuilderData.value.page = queryBuilderProps.value.page;
                }

                if (props.preserveScroll === "table-top") {
                    const offset = -8;
                    const top =
                        tableFieldset.value.getBoundingClientRect().top +
                        window.pageYOffset +
                        offset;

                    window.scrollTo({ top });
                }

                updates.value++;
                resetSelect();
            },
        }
    );
}
const isUpdatingFromInertia = ref(false);
watch(
    queryBuilderData,
    () => {
        if (!isUpdatingFromInertia.value) {
            console.log("QueryBuilder data changed, updating URL...");
            visit(location.pathname + "?" + generateNewQueryString());
        }
    },
    { deep: true }
);

const inertiaListener = () => {
    isUpdatingFromInertia.value = true;
    updates.value++;
    nextTick(() => {
        isUpdatingFromInertia.value = false;
    });
};

onMounted(() => {
    document.addEventListener("inertia:success", inertiaListener);
});

onUnmounted(() => {
    document.removeEventListener("inertia:success", inertiaListener);
});

//

function sortBy(column) {
    if (props.multipleSort) {
        const allsorts = queryBuilderData.value.sort
            ? queryBuilderData.value.sort.split(",")
            : [];
        var found = false;
        allsorts.forEach(function (allsort, index) {
            if (allsort == column) {
                allsorts[index] = `-${column}`;
                found = true;
            } else if (allsort == `-${column}`) {
                allsorts[index] = column;
                found = true;
            }
        });
        if (!found) {
            allsorts.push(column);
        }
        queryBuilderData.value.sort = allsorts.length
            ? allsorts.join(",")
            : column;
        if (props.advanceSort) {
            advSortFields.value = [];
            const allsorts2 = queryBuilderData.value.sort
                ? queryBuilderData.value.sort.split(",")
                : [];
            allsorts2.forEach(function (allsort2) {
                if (allsort2.includes("-")) {
                    advSortFields.value.push({ f: allsort2.slice(1), o: 2 });
                } else {
                    advSortFields.value.push({ f: allsort2, o: 1 });
                }
            });
        }
    } else {
        if (queryBuilderData.value.sort == column) {
            queryBuilderData.value.sort = `-${column}`;
            if (props.advanceSort) {
                advSortFields.value = [{ f: column, o: 2 }];
            }
        } else {
            queryBuilderData.value.sort = column;
            if (props.advanceSort) {
                advSortFields.value = [{ f: column, o: 1 }];
            }
        }
    }

    queryBuilderData.value.cursor = null;
    queryBuilderData.value.page = 1;
}

function show(key) {
    const intKey = findDataKey("columns", key);

    return !queryBuilderColumns.value[intKey].hidden;
}

function header(key) {
    const intKey = findDataKey("columns", key);
    const columnData = clone(queryBuilderProps.value.columns[intKey]);
    columnData.hidden = queryBuilderColumns.value[intKey].hidden;
    columnData.onSort = sortBy;

    return columnData;
}

function downloadCSV(csv, filename) {
    var csvFile;
    var downloadLink;

    // CSV file
    csvFile = new Blob([csv], { type: "text/csv" });

    // Download link
    downloadLink = document.createElement("a");

    // File name
    downloadLink.download = filename;

    // Create a link to the file
    downloadLink.href = window.URL.createObjectURL(csvFile);

    // Hide download link
    downloadLink.style.display = "none";

    // Add the link to DOM
    document.body.appendChild(downloadLink);

    // Click download link
    downloadLink.click();
}
function exportTableToCSV($event, excludelast) {
    var table = "datatable";
    var filename = props.resourceNeo.resourceName + ".csv";

    var csv = [];
    var rows = document.querySelectorAll("table#" + table + " tr");

    for (var i = 0; i < rows.length; i++) {
        var row = [],
            cols = rows[i].querySelectorAll(
                'td:not([style*="display:none"]):not([style*="display: none"]), th:not([style*="display:none"]):not([style*="display: none"])'
            );
        if (i > 0) {
            let dataId = Number(cols[0].getAttribute("data-id"));
            if (!selectedRows.value.includes(dataId)) {
                continue;
            }
        }

        if (excludelast == undefined) {
            var collength = cols.length;
        } else {
            var collength = cols.length - 1;
        }
        for (
            var j = props.resourceNeo.bulkActions ? 1 : 0;
            j < collength;
            j++
        ) {
            var intext = cols[j].innerText;
            intext = intext.indexOf(",") > -1 ? '"' + intext + '"' : intext;
            row.push(intext);
        }
        csv.push(row.join(","));
    }
    // Download CSV file
    downloadCSV(csv.join("\n"), filename);
}

function setFilterValue(val) {
    var d = new Date(val),
        month = d.getMonth() + 1,
        day = d.getDate(),
        year = d.getFullYear();
    editPopupModel.value = val ? year + "-" + month + "-" + day : "";
}

function columnToggle(clickedcolumns) {
    clickedcolumns.forEach(function (col) {
        var intKey = findDataKey("columns", col);
        tablewidthadjust(col, !queryBuilderColumns.value[intKey].hidden);
        queryBuilderColumns.value[intKey].hidden =
            !queryBuilderColumns.value[intKey].hidden;

        if (
            queryBuilderColumns.value[intKey].extra.showhide2 &&
            queryBuilderColumns.value[intKey].hidden
        ) {
            hidesecondlevel(queryBuilderColumns.value[intKey].extra.showhide2);
        }
    });
}
function hidesecondlevel(clickedcolumns) {
    clickedcolumns.forEach(function (col) {
        var intKey = findDataKey("columns", col);
        tablewidthadjust(col, !queryBuilderColumns.value[intKey].hidden);
        queryBuilderColumns.value[intKey].hidden = true;
    });
}

function showHideAll(showhide) {
    queryBuilderColumns.value.forEach((element) => {
        if (element.extra.hidden) {
            tablewidthadjust(element.key, showhide);
            element.hidden = showhide;
        }
    });
}
const elem = ref(null);

function tablewidthadjust(col, shd) {
    const nodeName = elem.value.nodeName;
    const table = nodeName === "TABLE" ? elem.value : elem.value.parentElement;
    const thead = table.querySelector("thead");
    const ths = thead.querySelectorAll("th");

    ths.forEach((th, index) => {
        if (shd && th.getAttribute("hkey") == col) {
            if (cutPx(table.style.width) > 0) {
                table.style.width =
                    cutPx(table.style.width) - cutPx(th.style.width) + "px";
            }
        }
    });
}
const cutPx = (str) => +str.replace("px", "");
const vColumnsResizable = {
    updated: (el) => {
        setTimeout(function () {
            const cutPx = (str) => +str.replace("px", "");

            if (!props.resizable) {
                return;
            }
            const nodeName = el.nodeName;
            if (["TABLE", "THEAD"].indexOf(nodeName) < 0) return;

            const table = nodeName === "TABLE" ? el : el.parentElement;
            const thead = table.querySelector("thead");
            const ths = thead.querySelectorAll("th");
            const barHeight =
                nodeName === "TABLE" ? table.offsetHeight : thead.offsetHeight;

            ths.forEach((th, index) => {
                if (th.style.width == "0px" && th.style.display != "none") {
                    table.style.width =
                        cutPx(table.style.width) + th.offsetWidth + "px";
                    th.style.width = th.offsetWidth + "px";
                }
                if (th.style.display == "none" && th.style.width != "0px") {
                    //table.style.width = (cutPx(table.style.width) - cutPx(th.style.width)) + 'px';
                    th.style.width = "0px";
                }
            });

            const resizeContainer = table.parentElement.querySelector("div");
            table.style.position = "relative";
            table.style.width = table.offsetWidth + "px";
            resizeContainer.style.position = "relative";
            resizeContainer.style.width = table.offsetWidth + "px";

            ths.forEach((th, index) => {
                th.style.width = th.offsetWidth + "px";

                if (index + 1 >= ths.length) return;

                const nextTh = ths[index + 1];
                const bars = resizeContainer.querySelectorAll(
                    ".columns-resize-bar"
                );
                const bar = bars[index];

                bar.style.position = "absolute";
                bar.style.left = nextTh.offsetLeft - 4 + "px";
                bar.style.top = 0;
                bar.style.height = barHeight + "px";
                bar.style.width = "8px";
                bar.style.cursor = "col-resize";
                bar.style.zIndex = 1;
            });
        }, 5);
    },

    mounted: (el) => {
        elem.value = el;
        if (!props.resizable) {
            return;
        }
        makeresizable(el);
    },
};

function makeresizable(el) {
    const nodeName = el.nodeName;
    if (["TABLE", "THEAD"].indexOf(nodeName) < 0) return;
    const table = nodeName === "TABLE" ? el : el.parentElement;
    const thead = table.querySelector("thead");
    const ths = thead.querySelectorAll("th");
    const barHeight =
        nodeName === "TABLE" ? table.offsetHeight : thead.offsetHeight;

    const resizeContainer = document.createElement("div");
    table.style.position = "relative";
    table.style.width = table.offsetWidth + "px";
    resizeContainer.style.position = "relative";
    resizeContainer.style.width = table.offsetWidth + "px";
    resizeContainer.className = "vue-columns-resizable";
    table.parentElement.insertBefore(resizeContainer, table);

    let moving = false;
    let movingIndex = 0;

    ths.forEach((th, index) => {
        th.style.width =
            cutPx(th.style.width) > 0 ? th.style.width : th.offsetWidth + "px";

        if (index + 1 >= ths.length) return;

        const nextTh = ths[index + 1];
        const bar = document.createElement("div");

        bar.style.position = "absolute";
        bar.style.left = nextTh.offsetLeft - 4 + "px";
        bar.style.top = 0;
        bar.style.height = barHeight + "px";
        bar.style.width = "8px";
        bar.style.cursor = "col-resize";
        bar.style.zIndex = 1;
        bar.className = "columns-resize-bar";

        bar.addEventListener("mousedown", () => {
            moving = true;
            movingIndex = index;
            document.body.style.cursor = "col-resize";
            document.body.style.userSelect = "none";
        });

        resizeContainer.appendChild(bar);
    });

    const bars = resizeContainer.querySelectorAll(".columns-resize-bar");

    document.addEventListener("mouseup", () => {
        if (!moving) return;

        moving = false;
        document.body.style.cursor = "";
        document.body.style.userSelect = "";

        bars.forEach((bar, index) => {
            const th = ths[index];
            const nextTh = ths[index + 1];
            th.style.width = th.offsetWidth + "px";
            bar.style.left = nextTh.offsetLeft - 4 + "px";
        });
    });

    const handleResize = (e) => {
        if (moving) {
            var cnt = 0;
            while (
                movingIndex > 0 &&
                cutPx(ths[movingIndex + cnt].style.width) == 0
            ) {
                cnt--;
            }
            const th = ths[movingIndex + cnt];
            const nextTh = ths[movingIndex + 1];
            const bar = bars[movingIndex];
            th.style.width = cutPx(th.style.width) + e.movementX + "px";
            table.style.width = cutPx(table.style.width) + e.movementX + "px";
            //nextTh.style.width = cutPx(nextTh.style.width) - e.movementX + 'px';
            bar.style.left = nextTh.offsetLeft - 4 + e.movementX + "px";
        }
    };

    resizeContainer.addEventListener("mousemove", handleResize);
    table.addEventListener("mousemove", handleResize);
}

defineExpose({
    resetSelect,
    showHideAll,
    focustoDisplayCell,
});

watch(
    selectedRows,
    () => {
        if (
            props.seleLimit > 0 &&
            selectedRows.value.length > props.seleLimit
        ) {
            selectedRows.value.pop();
            useToast().error(
                "You can not select more than " + props.seleLimit + " record!!"
            );
        }
    },
    { deep: true }
);

// In @/components/DataTable/Table.vue

const saveColumnState = (columns) => {
    // Don't save to localStorage if vcolumns parameter is present in URL
    const queryStringData = qs.parse(location.search.substring(1));
    const vcolumnsParam = queryStringData.vcolumns;
    
    if (vcolumnsParam && vcolumnsParam.trim() !== '') {
        // Skip saving when vcolumns parameter is present
        return;
    }
    
    if (typeof localStorage !== "undefined") {
        // Save state with unique key based on table name/route
        const storageKey = `tg-cl-${props.resourceNeo.resourceName}`;
        localStorage.setItem(storageKey, JSON.stringify(columns));
    }
};

const loadColumnState = () => {
    if (typeof localStorage !== "undefined") {
        const storageKey = `tg-cl-${props.resourceNeo.resourceName}`;
        const savedState = localStorage.getItem(storageKey);
        if (savedState) {
            return JSON.parse(savedState);
        }
    }
    return null;
};

// In your setup/mounted code:
onMounted(() => {
    // Parse URL parameters
    const queryStringData = qs.parse(location.search.substring(1));
    const vcolumnsParam = queryStringData.vcolumns;
    
    if (vcolumnsParam) {
        // If vcolumns parameter exists in URL, use it temporarily
        const visibleColumns = vcolumnsParam.split('::');
        
        queryBuilderColumns.value.forEach((element) => {
            // Set hidden to true for all columns not in the vcolumns list
            element.hidden = !visibleColumns.includes(element.key);
        });
    } else {
        // Load saved column state from localStorage (normal behavior)
        const savedColumns = loadColumnState();
        if (savedColumns) {
            queryBuilderColumns.value.forEach((element) => {
                let savedCol = savedColumns.find((c) => c.key === element.key);
                if (savedCol) {
                    element.hidden = savedCol.hidden;
                }
            });
        }
    }
});

// Add watcher for column visibility changes
watch(
    () => queryBuilderColumns,
    (newColumns) => {
        // Save state when columns visibility changes (only if vcolumns param is not present)
        saveColumnState(
            newColumns.value.map((col) => ({
                key: col.key,
                hidden: col.hidden,
            }))
        );
    },
    { deep: true }
);
const sumTotal = computed(() => {
    let sumArray = [];
    forEach(queryBuilderProps.value.columns, (column) => {
        if (column.extra.showTotal) {
            sumArray[column.key] = 0;
        }
    });

    forEach(resourceData.value, (item, key) => {
        for (var akey in sumArray) {
            sumArray[akey] =
                sumArray[akey] +
                (isNaN(parseFloat(item[akey])) ? 0 : parseFloat(item[akey]));
        }
    });

    return sumArray;
});

const calTotal = (key) => {
    // return only if key exist in sumArray
    if (sumTotal.value[key] === undefined) {
        return "";
    } else {
        return sumTotal.value[key].toFixed(3);
    }
};
</script>
