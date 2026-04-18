import {
    mdiAccountSupervisor,
    mdiViewDashboard,
    mdiAccountBoxMultiple,
    mdiAccountGroup,
    mdiMonitorEye,
    mdiAccountEye,
    mdiArchiveEye,
    mdiHandshake,
    mdiTruckDelivery,
    mdiAccountMultiple,
    mdiCurrencyInr,
    mdiScale,
    mdiGroup,
    mdiMapMarkerMultipleOutline,
    mdiAccountTie,
    mdiDragVariant,
    mdiFormatListBulletedType,
    mdiBasketFill,
    mdiAllInclusive,
    mdiCartPlus,
    mdiCartArrowUp,
    mdiInvoiceList,
} from "@mdi/js";

export default [
    {
        route: "dashboard",
        icon: mdiViewDashboard,
        label: "Dashboard",
    },
    {
        label: "Masters",
        icon: mdiBasketFill,
        menu: [
            {
                route: "munit.index",
                label: "Measurement Unit",
                icon: mdiScale,
                resource: "munit",
            },
            {
                route: "pgroup.index",
                label: "Product Group",
                icon: mdiGroup,
                resource: "pgroup",
            },
            {
                route: "location.index",
                label: "Locations",
                icon: mdiMapMarkerMultipleOutline,
                resource: "location",
            },
            {
                route: "expuser.index",
                label: "Exp. Users",
                icon: mdiAccountTie,
                resource: "expuser",
            },
            {
                route: "expcate.index",
                label: "Exp. Category",
                icon: mdiDragVariant,
                resource: "expcate",
            },
            {
                route: "consumableInternalName.index",
                label: "Product Internal Name",
                icon: mdiFormatListBulletedType,
                resource: "consumableInternalName",
            },
            {
                route: "openStock.index",
                icon: mdiInvoiceList,
                label: "Open Stock",
                resource: "openStock",
            },
            {
                label: "Cost Sheet",
                icon: mdiInvoiceList,
                menu: [
                    {
                        route: "signageCostSheet.index",
                        label: "Signage",
                        icon: mdiInvoiceList,
                        resource: "signageCostSheet",
                    },
                    {
                        route: "cabinetCostSheet.index",
                        label: "Cabinet",
                        icon: mdiInvoiceList,
                        resource: "cabinetCostSheet",
                    },
                    {
                        route: "lettersCostSheet.index",
                        label: "Letters",
                        icon: mdiInvoiceList,
                        resource: "lettersCostSheet",
                    },
                ],
            },
        ],
    },
    {
        label: "User & Roles",
        icon: mdiAccountSupervisor,
        menu: [
            {
                route: "user.index",
                label: "Users",
                icon: mdiAccountBoxMultiple,
                resource: "user",
            },
            {
                route: "role.index",
                label: "Role",
                icon: mdiAccountGroup,
                resource: "role",
            },
        ],
    },
    {
        label: "Logs",
        icon: mdiMonitorEye,
        menu: [
            {
                route: "signinLog.index",
                label: "Signin Logs",
                icon: mdiAccountEye,
                resource: "signinLog",
            },
            {
                route: "activityLog.index",
                label: "Activity Logs",
                icon: mdiArchiveEye,
                resource: "activityLog",
            },
        ],
    },
    {
                route: "product.index",
                icon: mdiAllInclusive,
                label: "Products",
                resource: "product",
    },
    {
        label: "Associates",
        icon: mdiHandshake,
        menu: [
            {
                route: "supplier.index",
                icon: mdiTruckDelivery,
                label: "Suppliers",
                resource: "supplier",
            },
            {
                route: "client.index",
                icon: mdiAccountMultiple,
                label: "Clients",
                resource: "client",
            },
        ],
    },
    {
        route: "opening.index",
        icon: mdiCartPlus,
        label: "Opening",
        resource: "opening",
    },
    {
        route: "purchase.index",
        icon: mdiBasketFill,
        label: "Purchases",
        resource: "purchase",
    },
    {
        route: "enquiry.index",
        icon: mdiInvoiceList,
        label: "Enquiry",
        resource: "enquiry",
    },
    {
        route: "salesOrder.index",
        icon: mdiCartPlus,
        label: "Sales Order",
        resource: "salesOrder",
    },
    {
        route: "outward.index",
        icon: mdiCartArrowUp,
        label: "Outwards",
        resource: "outward",
    },
    {
        route: "expense.index",
        icon: mdiCurrencyInr,
        label: "Expense",
        resource: "expense",
    },      
    {
        label: "Stocks",
        icon: mdiBasketFill,
        menu: [
            
            {
                route: "stocks.index",
                routeParams: { "filter[sgroup]": "Stock Item" },
                icon: mdiInvoiceList,
                label: "Stocks Summary",
                resource: "stocks",
            },
            {
                route: "stocks.owner",
                routeParams: { "filter[sgroup]": "Stock Item" },
                icon: mdiInvoiceList,
                label: "Stocks Ownership",
                resource: "stocks",
            },
            {
                route: "stocks.level",
                routeParams: { "filter[sgroup]": "Stock Item" },
                icon: mdiInvoiceList,
                label: "Stocks Level",
                resource: "stocks",
            },
        ],
    },
    {
        label: "Reports",
        icon: mdiInvoiceList,
        menu: [
            {
                route: "consumableInternalNameReport.index",
                label: "Product Internal Name Report",
                icon: mdiFormatListBulletedType,
                resource: "consumableInternalNameReport",
            },
        ],
    },
];
