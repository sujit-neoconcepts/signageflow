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
    mdiClipboardCheckOutline,
    mdiCalendarCheck,
    mdiSitemap,
    mdiBriefcase,
    mdiCashMultiple,
    mdiHandCoin,
    mdiReceiptTextCheck,
    mdiBankTransfer,
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
                route: "consumableInternalName.index",
                label: "Internal Name",
                icon: mdiFormatListBulletedType,
                resource: "consumableInternalName",
            },
            {
                route: "consumableInternalNameGroup.index",
                label: "Internal Name Group",
                icon: mdiFormatListBulletedType,
                resource: "consumableInternalNameGroup",
            },
            {
                route: "pgroup.index",
                label: "Product Group",
                icon: mdiGroup,
                resource: "pgroup",
            },
            {
                route: "product.index",
                icon: mdiAllInclusive,
                label: "Products Bridge",
                resource: "product",
            },
            {
                route: "openStock.index",
                icon: mdiInvoiceList,
                label: "Open Stock",
                resource: "openStock",
            },
            {
                route: "openStockGroup.index",
                icon: mdiInvoiceList,
                label: "Open Stock Group",
                resource: "openStock",
            },
            {
                label: "Users & Roles",
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
                route: "expuser.index",
                label: "Expense Users",
                icon: mdiAccountTie,
                resource: "expuser",
            },
            {
                route: "expcate.index",
                label: "Expense Category",
                icon: mdiDragVariant,
                resource: "expcate",
            },
            {
                route: "munit.index",
                label: "Measurement Unit",
                icon: mdiScale,
                resource: "munit",
            },
            {
                route: "location.index",
                label: "Locations",
                icon: mdiMapMarkerMultipleOutline,
                resource: "location",
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
        ],
    },
    {
        label: "Tasks Manager",
        icon: mdiClipboardCheckOutline,
        menu: [
            {
                route: "workflow.index",
                label: "Workflows",
                icon: mdiSitemap,
                resource: "workflow",
            },
            {
                route: "job.index",
                label: "Jobs",
                icon: mdiBriefcase,
                resource: "job",
            },
            {
                route: "task.index",
                label: "Tasks",
                icon: mdiClipboardCheckOutline,
                resource: "task",
            },
            {
                route: "task.myTasks",
                label: "My Tasks",
                icon: mdiCalendarCheck,
                resource: "task",
                permission: "task_MyTasksList",
            },
        ],
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
        route: "purchase.index",
        icon: mdiBasketFill,
        label: "Purchases",
        resource: "purchase",
    },
    
    
    {
        route: "outward.index",
        icon: mdiCartArrowUp,
        label: "Outwards",
        resource: "outward",
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
        route: "expense.index",
        icon: mdiCurrencyInr,
        label: "Expense",
        resource: "expense",
    },      
    {
        route: "opening.index",
        icon: mdiCartPlus,
        label: "Opening",
        resource: "opening",
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
                label: "Price List",
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
            {
                route: "consumableInternalNameGroupReport.index",
                label: "Product Internal Name Group Report",
                icon: mdiFormatListBulletedType,
                resource: "consumableInternalNameGroupReport",
            },
        ],
    },
    {
        label: "HRMS",
        icon: mdiAccountGroup,
        menu: [
            {
                label: "Core & Organization",
                icon: mdiSitemap,
                menu: [
                    {
                        route: "hrms.org.index",
                        label: "Org Hierarchy",
                        icon: mdiSitemap,
                        resource: "hrmsOrg",
                    },
                    {
                        route: "hrms.employees.index",
                        label: "Employees",
                        icon: mdiAccountBoxMultiple,
                        resource: "hrmsEmployee",
                    },
                ],
            },
            {
                label: "Attendance Management",
                icon: mdiCalendarCheck,
                menu: [
                    {
                        route: "hrms.attendance.dashboard",
                        label: "Attendance Dashboard",
                        icon: mdiViewDashboard,
                        permission: "hrmsDashboard_view",
                    },
                    {
                        route: "hrms.attendance.adjustments",
                        label: "Attendance & OT Approvals",
                        icon: mdiClipboardCheckOutline,
                        resource: "hrmsAttendance",
                    },
                    {
                        route: "hrms.attendance.maintenance",
                        label: "Bulk Maintenance & Biometric",
                        icon: mdiAllInclusive,
                        permission: "hrmsAttendance_edit",
                    },
                    {
                        route: "hrms.attendance.reports",
                        label: "Attendance Reports",
                        icon: mdiInvoiceList,
                        resource: "hrmsAttendanceReport",
                    },
                    {
                        route: "hrms.attendance.setup",
                        label: "Attendance Setup",
                        icon: mdiCalendarCheck,
                        resource: "hrmsAttendanceSetup",
                    },
                ],
            },
            {
                label: "Leave & Holidays",
                icon: mdiClipboardCheckOutline,
                menu: [
                    {
                        route: "hrms.leave.dashboard",
                        label: "Leave Dashboard",
                        icon: mdiViewDashboard,
                        resource: "hrmsLeave",
                    },
                    {
                        route: "hrms.leave.approvals",
                        label: "Leave Approvals",
                        icon: mdiClipboardCheckOutline,
                        resource: "hrmsLeave",
                    },
                    {
                        route: "hrms.leave.actions",
                        label: "Leave Admin & Year-End",
                        icon: mdiAccountTie,
                        permission: "hrmsLeave_applyOnBehalf",
                    },
                    {
                        route: "hrms.leave.reports",
                        label: "Leave Reports",
                        icon: mdiInvoiceList,
                        resource: "hrmsLeaveReport",
                    },
                    {
                        route: "hrms.leave.setup",
                        label: "Leave & Calendar Setup",
                        icon: mdiCalendarCheck,
                        resource: "hrmsLeaveSetup",
                    },
                ],
            },
            {
                label: "Payroll & Finance",
                icon: mdiCurrencyInr,
                menu: [
                    {
                        route: "hrms.payroll.payruns",
                        label: "PayRuns (3-Stage)",
                        icon: mdiCurrencyInr,
                        resource: "hrmsPayroll",
                    },
                    {
                        route: "hrms.payroll.loans",
                        label: "Loan Master",
                        icon: mdiHandCoin,
                        resource: "hrmsLoan",
                    },
                    {
                        route: "hrms.payroll.claims",
                        label: "E-Claims",
                        icon: mdiReceiptTextCheck,
                        resource: "hrmsClaim",
                    },
                    {
                        route: "hrms.payroll.bankTally",
                        label: "Bank & Tally GL",
                        icon: mdiBankTransfer,
                        permission: "hrmsPayroll_list",
                    },
                    {
                        route: "hrms.payroll.reports",
                        label: "Payroll Reports",
                        icon: mdiInvoiceList,
                        resource: "hrmsPayrollReport",
                    },
                    {
                        route: "hrms.payroll.setup",
                        label: "Payroll Setup",
                        icon: mdiCashMultiple,
                        resource: "hrmsPayrollSetup",
                    },
                ],
            },
            {
                label: "Administration & Docs",
                icon: mdiMonitorEye,
                menu: [
                    {
                        route: "hrms.admin.index",
                        label: "HRMS Admin & Audit",
                        icon: mdiMonitorEye,
                        resource: "hrmsAdmin",
                    },
                    {
                        route: "hrms.docs.userGuide",
                        label: "User Guide",
                        icon: mdiFormatListBulletedType,
                        permission: "hrmsDashboard_list",
                    },
                    {
                        route: "hrms.docs.developerGuide",
                        label: "Developer Guide",
                        icon: mdiMonitorEye,
                        permission: "hrmsAdmin_list",
                    },
                ],
            },
        ],
    },

];
