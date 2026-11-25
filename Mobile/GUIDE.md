# SignageFlow Mobile — Complete User & Developer Guide

> **Application**: SignageFlow Mobile Companion  
> **Organization**: Tiwari Industries  
> **Platforms**: Android (Package ID: `com.tiwariindustries.signage`) & iOS (Bundle ID: `com.tiwariindustries.signage`)  
> **Technology Stack**: Flutter (Dart `^3.10.7`), Material 3, Laravel Sanctum REST API, Firebase Cloud Messaging (FCM v1)  
> **Target Audience**: Field Engineers, Fabrication Specialists, Supervisors, Managers, and Software Developers

---

## Table of Contents

- [1. Executive Overview](#1-executive-overview)
- [PART I: USER GUIDE](#part-i-user-guide)
  - [2. Installation & Prerequisites](#2-installation--prerequisites)
  - [3. Authentication & Sign In](#3-authentication--sign-in)
  - [4. App Layout & Global Navigation](#4-app-layout--global-navigation)
  - [5. Home Dashboard ("Today Console")](#5-home-dashboard-today-console)
  - [6. My Tasks Console](#6-my-tasks-console)
  - [7. Task Details & Work Execution](#7-task-details--work-execution)
    - [7.1 Viewing Task Details & Job Context](#71-viewing-task-details--job-context)
    - [7.2 Role-Based Stage Transitions](#72-role-based-stage-transitions)
    - [7.3 Completing Tasks, Attachments & Voice Notes](#73-completing-tasks-attachments--voice-notes)
    - [7.4 Downloading Task & Job Attachments](#74-downloading-task--job-attachments)
    - [7.5 Logging Task-Linked Expenses](#75-logging-task-linked-expenses)
    - [7.6 Discussion, Comments & Voice Note Playback](#76-discussion-comments--voice-note-playback)
  - [8. Expense Ledger & Financial Position](#8-expense-ledger--financial-position)
    - [8.1 Ledger Summary & Cash Position](#81-ledger-summary--cash-position)
    - [8.2 Searching & Date Range Filters](#82-searching--date-range-filters)
    - [8.3 Logging Standalone Expenses & Deposits](#83-logging-standalone-expenses--deposits)
  - [9. Notifications & Alerts](#9-notifications--alerts)
- [PART II: DEVELOPER GUIDE](#part-ii-developer-guide)
  - [10. Architecture & Directory Structure](#10-architecture--directory-structure)
  - [11. State Management & Secure Storage](#11-state-management--secure-storage)
  - [12. Network Layer & API Client](#12-network-layer--api-client)
  - [13. Backend Laravel API Specification](#13-backend-laravel-api-specification)
    - [13.1 Authentication Endpoints](#131-authentication-endpoints)
    - [13.2 Tasks Endpoints](#132-tasks-endpoints)
    - [13.3 Expense Endpoints](#133-expense-endpoints)
    - [13.4 Push Token & Notifications Endpoints](#134-push-token--notifications-endpoints)
  - [14. Business Logic, Workflows & Automation](#14-business-logic-workflows--automation)
    - [14.1 Task Lifecycle & Multi-Assignee Aggregation](#141-task-lifecycle--multi-assignee-aggregation)
    - [14.2 Sequential Stage Auto-Start (`start_on_previous_complete`)](#142-sequential-stage-auto-start-start_on_previous_complete)
    - [14.3 Job Completion Auto-Detection](#143-job-completion-auto-detection)
    - [14.4 Two-Day Backdate Rule & Permissions](#144-two-day-backdate-rule--permissions)
    - [14.5 Audio & Media Classification](#145-audio--media-classification)
  - [15. Push Notification Pipeline (FCM v1)](#15-push-notification-pipeline-fcm-v1)
  - [16. Build, Release & Versioning Procedures](#16-build-release--versioning-procedures)
    - [16.1 Local Development Setup](#161-local-development-setup)
    - [16.2 Automated Version Bump & App Bundle Build](#162-automated-version-bump--app-bundle-build)
    - [16.3 Android Keystore & Code Signing](#163-android-keystore--code-signing)
    - [16.4 iOS Deployment Configuration](#164-ios-deployment-configuration)
    - [16.5 App Icon Generation](#165-app-icon-generation)
  - [17. Common Troubleshooting & Diagnostics](#17-common-troubleshooting--diagnostics)

---

## 1. Executive Overview

**SignageFlow Mobile** is the native operational companion for the SignageFlow enterprise resource planning (ERP) platform. Designed for field technicians, installation crew members, shop-floor operators, and site supervisors, it brings real-time job execution, progress updates, rich audio/file communications, and financial expense tracking directly into the palms of field teams.

```
+-----------------------------------------------------------------------------------+
|                              SignageFlow Mobile App                               |
|                  (Flutter Material 3 / Android & iOS Devices)                     |
+-----------------------------------------------------------------------------------+
        |                                       |                          |
        | Bearer Sanctum Token                  | Push Registration Token  | Native Media
        v                                       v                          v
+-------------------------------+      +-------------------+      +------------------+
|   Laravel REST Backend        |      | Firebase Cloud    |      | Device Sensors & |
|   (/api/mobile/*)             |      | Messaging (FCM v1)|      | File Storage     |
+-------------------------------+      +-------------------+      +------------------+
        |                                       ^                          |
        v                                       |                          v
+-------------------------------+               |                 +------------------+
| MySQL Database                |---------------+                 | Audio Recorder   |
| - Tasks & Assignee Pivots     |  FCM Push Notification Service  | Document Openers |
| - Job Attachments & Comments  |                                 | Camera / Gallery |
| - Expense & Ledger Balances   |                                 +------------------+
| - Notifications & Device Keys |
+-------------------------------+
```

### Key Capabilities

1. **Task Execution Console**: Accept tasks, initiate work when scheduled start time arrives, submit completion feedback, attach photos/drawings, and record native AAC voice notes.
2. **Conditional Compliance Safeguards**: Enforces mandatory Enquiry and Sales Order linkings prior to completion when configured on the task. Prevents premature task starts prior to designated schedule times.
3. **Automated Stage Handoff**: Automatically starts subsequent job stages (`start_on_previous_complete`) and triggers instant mobile push notifications to next-stage assignees upon task completion.
4. **Task & Job Document Access**: Securely download and open engineering drawings, client mockups, vector artwork, and specification sheets directly from task and parent job records.
5. **Interactive Expense Ledger**: Record both task-specific expenses and standalone field expenses with category classification, worker tagging, and immediate recalculation of running cash balances (Opening Balance + Deposits - Expenses = Closing Balance).
6. **Voice Note Collaboration**: Record, upload, and stream high-clarity voice notes within task discussion threads for hands-free field updates.
7. **Real-time Push Notifications**: Receive immediate push alerts on Android and iOS devices when tasks are assigned, updated, commented upon, or due soon.

---

# PART I: USER GUIDE

---

## 2. Installation & Prerequisites

### System Requirements
- **Android**: Android 5.0 (Lollipop, API Level 21) or higher.
- **iOS**: iOS 12.0 or higher.
- **Network**: Cellular data (4G/5G) or Wi-Fi connection with access to the SignageFlow API domain.

### Required Device Permissions
When launching specific features, the operating system will request permission grants:
- **Microphone / Audio Recording (`RECORD_AUDIO` / `NSMicrophoneUsageDescription`)**: Required to record voice notes in task comments and completion reports.
- **Notifications (`POST_NOTIFICATIONS`)**: Required to receive job alerts, task assignments, reminders, and comment notices.
- **Storage / File Access**: Required to select images, PDFs, or files for upload and to download job drawings.

---

## 3. Authentication & Sign In

```
+-------------------------------------------------------------+
|                      SignageFlow                            |
|                   Tiwari Industries                         |
|                                                             |
|  [ Email Address: john@tiwariindustries.com              ]  |
|  [ Password:      ••••••••••••••••                       ]  |
|                                                             |
|                   [ Sign in securely ]                      |
+-------------------------------------------------------------+
```

1. **Open the Application**: On initial launch, the app verifies saved credentials stored in the device's hardware-backed secure keychain.
2. **Enter Credentials**:
   - **Email**: Enter your official SignageFlow work email address.
   - **Password**: Enter your account password. Toggle the eye icon to verify input visibility.
3. **Tap "Sign in securely"**:
   - The app verifies your credentials against the server.
   - **Access Validation**: Your account must have either `task_MyTasksList` (to view/manage assigned tasks) or `expense_list` (to view/record expenses). Accounts without mobile permissions will see: *"You do not have mobile app access."*
   - Once validated, a secure authentication token is saved to your device. You will stay signed in until you explicitly choose to log out.

---

## 4. App Layout & Global Navigation

SignageFlow Mobile uses an intuitive three-tier interface layout:

```
+-------------------------------------------------------------+
| [Logo] SignageFlow                         [Bell Badge (3)] |
|        Today Console                                        |
+-------------------------------------------------------------+
|                                                             |
|                    ACTIVE PAGE CONTENT                      |
|                                                             |
+-------------------------------------------------------------+
|       ( • ) Home        [ √ ] Tasks        [ $ ] Expenses   |
+-------------------------------------------------------------+
```

### Top Navigation Bar
- **App Logo (Top-Left)**: Tap the Tiwari Industries icon to slide open the side navigation drawer.
- **Title & Subtitle**: Displays "SignageFlow" along with your currently selected module name.
- **Notification Bell (Top-Right)**: Displays a red badge with the count of unread notifications. Tap the bell at any time to open the full Notifications Center.

### Floating Bottom Navigation Bar
- **Home**: The executive dashboard summarizing your daily tasks, overdue items, and financial cash position.
- **Tasks**: Your complete task work console with status filters, search, and detail screens.
- **Expenses**: The financial ledger showing deposits, expenses, and cash-in-hand balances.

### Side Navigation Drawer
Slide from the left edge or tap the top-left logo to open the navigation drawer:
- Organization branding header (Tiwari Industries).
- Direct links to **Home**, **My Tasks**, **Expenses**, and **Notifications**.
- **Logout Action**: Securely invalidates your session token on the server and returns you to the login screen.

---

## 5. Home Dashboard ("Today Console")

The Home Dashboard provides an immediate operational snapshot of your day:

1. **Today Console Banner**: Quick orientation header displaying Tiwari Industries branding.
2. **Interactive Metric Cards**:
   - **Active Tasks**: Count of tasks currently assigned to you that require action (in `pending`, `accepted`, or `in_progress` status). Tap to jump to the Tasks screen.
   - **Overdue Tasks**: Highlighted in bold red. Counts tasks whose due dates have passed and are not yet completed. Tap to view and resolve urgent items.
   - **Total Tasks**: Total count of all tasks where you are either an active assignee or an in-the-loop observer.
   - **Total Deposit**: Sum of all money deposited/credited to your account for the current day.
   - **Total Expense**: Sum of all expenses logged by you for the current day.
   - **Closing Balance**: Net cash-in-hand position (`Opening Balance + Deposit - Expense`).
3. **Quick Action Shortcuts**:
   - **Open task work console**: Jump straight into active work orders.
   - **Open expense ledger**: Jump straight into daily financial logging.
4. **Pull-to-Refresh**: Swipe down anywhere on the screen to refresh dashboard counters in real time.

---

## 6. My Tasks Console

The Tasks screen provides a comprehensive list of all assigned tasks and tasks where you are kept in the loop:

```
+-------------------------------------------------------------+
| Assigned: 12  |  Active: 5  |  Overdue: 2  |  Done: 7       |
+-------------------------------------------------------------+
| [Q Search task title or description...]               [Tune]|
+-------------------------------------------------------------+
| ( All ) [ Pending ] [ In Progress ] [ Over Due ] [ Viewer ] |
+-------------------------------------------------------------+
| [!] OVERDUE   IN PROGRESS   HIGH   Due 21-11-2026 18:00     |
| Main Entrance ACP Signage                                   |
| Metro Station Phase 2  •  L&T Construction        [Expense] |
+-------------------------------------------------------------+
```

### Metrics Summary Header
- **Assigned**: Total number of tasks assigned to your user account.
- **Active**: Tasks currently awaiting work or actively underway.
- **Overdue**: Urgent tasks past their due deadline.
- **Done**: Tasks marked as completed, verified, or closed.

### Filtering & Searching
- **Search Bar**: Type any keyword from a task title or description and tap search.
- **Status Filter Chips**:
  - `All`: View every task in your workspace.
  - `Pending`: New tasks waiting for you to accept or start.
  - `In Progress`: Tasks currently underway.
  - `Over Due`: Urgent tasks that have passed their deadline.
  - `Viewer`: Tasks where you are added for informational visibility only (read-only observer).
  - `Completed`: Tasks you have finished and submitted for supervisor verification.

### Task Cards Overview
Each task card displays:
- **Title**: Clear name of the task.
- **Job & Client**: Associated parent Job title and Client name.
- **Status Badge**: Color-coded pill (`Pending` in Amber, `In Progress` in Purple, `Completed` in Green, `Verified` in Teal).
- **Priority Badge**: Urgency level (`URGENT` in Red, `HIGH` in Deep Orange, `MEDIUM` in Sky Blue, `LOW` in Slate).
- **Due Date Pill**: Displays target completion date and time.
- **Overdue Highlight**: When overdue, the card displays a red border, light-red background tint, and an `OVERDUE` pill.
- **Expense Tag**: Displays a green `Expense` badge if the task mandates expense logging.

---

## 7. Task Details & Work Execution

Tap any task card to open the **Task Detail Screen**.

### 7.1 Viewing Task Details & Job Context
- **Header Section**: Full task title, status, priority, and due date.
- **Job Information**: Displays associated job title and client name.
- **Schedule**: Displays scheduled Start Date and Estimated Work Hours.
- **Task Description**: Comprehensive instructions, dimensions, specifications, or notes provided by the task creator.

---

### 7.2 Role-Based Stage Transitions

The **Work Console** action panel dynamically displays the valid next action based on your assignment role:

```
+-------------------------------------------------------------+
| WORK CONSOLE                                                |
|                                                             |
| Status: PENDING                                             |
| [ > Accept and Start Task ]                                 |
|                                                             |
| Status: IN PROGRESS                                         |
| [ √ Submit Task for Verification ]                          |
+-------------------------------------------------------------+
```

1. **Observer / Viewer Role**:
   - If you are only looped into the task (not an assignee), the action buttons are hidden. You have full read-only access to files, comments, and status history.
2. **Pending Task**:
   - Displays **Accept and Start Task**.
   - *Scheduled Start Date Safeguard*: If the task creator configured a future start date, the button is disabled and displays `Starts <date>`. You cannot start the task prematurely.
   - Tapping changes your status to `in_progress` and logs an automated audit entry.
3. **Accepted Task**:
   - Displays **Start Work** to move the task into active `in_progress` execution.
4. **In-Progress Task**:
   - Displays **Submit Task for Verification**. Tap to open the completion modal.

---

### 7.3 Completing Tasks, Attachments & Voice Notes

Tapping **Submit Task for Verification** opens the completion sheet:

```
+-------------------------------------------------------------+
| Complete Task                                               |
|                                                             |
| [ Enquiry Number: ENQ-2026-089                      (v) ]   |
| [ Sales Order Number: SO-2026-042                   (v) ]   |
|                                                             |
| Work summary:                                               |
| [ Fabrication finished. Acrylic letters mounted and tested] |
|                                                             |
| [ + Attach files (2) ]          [ (o) Voice Note / Stop ]   |
|                                                             |
|                    [ Submit ]                               |
+-------------------------------------------------------------+
```

1. **Mandatory Enquiry / Sales Order Validation**:
   - If the task configuration requires an Enquiry Number (`need_enquiry_number`), a searchable autocomplete dropdown appears. You must select a valid enquiry number.
   - If the task configuration requires a Sales Order Number (`need_sales_order_number`), a searchable autocomplete dropdown appears. You must select a valid sales order number.
2. **Work Summary**: Enter remarks or notes describing the work completed on site.
3. **Attach Files**:
   - Tap **Attach files** to open your device file picker or photo gallery.
   - Attach photos of installed signage, completion receipts, or test reports. You can select multiple files.
4. **Record Voice Note**:
   - Tap **Voice Note** to start recording audio directly through your device microphone.
   - The button pulses red with label `Stop Rec`.
   - Speak your update clearly.
   - Tap `Stop Rec` to finalize the recording. The `.m4a` audio file is automatically appended to your submission.
5. **Tap Submit**:
   - The task status updates to `completed`.
   - Your feedback, files, and voice notes are stored.
   - The task creator receives an immediate push notification and email alert.
   - If configured with automated workflow chaining, the next stage of the job automatically starts!

---

### 7.4 Downloading Task & Job Attachments

The detail screen includes two dedicated attachment panels:
- **Task Attachments**: Files specifically uploaded to this task.
- **Job Attachments**: Engineering drawings, CAD files, structural plans, or client artwork attached to the parent job.

**To view an attachment**:
- Tap on any file row.
- The app downloads the file securely using your authenticated session and launches your device's native viewer (PDF viewer, image viewer, or document editor).

---

### 7.5 Logging Task-Linked Expenses

If a task has `need_expense` enabled, a dedicated **Task Expenses** panel is visible:

```
+-------------------------------------------------------------+
| Task Expenses                             Total: ₹ 1,450.00 |
| • Travel Allowance — ₹ 450.00                               |
| • Hardware & Fasteners — ₹ 1,000.00                         |
|-------------------------------------------------------------|
| Date: [ 22-11-2026 ] [Edit]                                 |
| Amount: [ 350.00 ]                                          |
| Category: [ Transportation                             (v) ]|
| Location / Job Details: [ Metro Phase 2 Site              ] |
| Done By: [ Ramesh Kumar                                (v) ]|
| Details: [ Auto fare from factory to site                 ] |
|                                                             |
|                    [ Log Expense ]                          |
+-------------------------------------------------------------+
```

1. **View Existing Expenses**: All expenses linked to this task are listed with amount, category, date, and worker. A running total is shown at the top-right.
2. **Log a New Expense**:
   - **Date**: Defaults to today. Tap to change. *(Note: Dates older than 2 days are blocked unless you have back-date permission).*
   - **Amount**: Enter the numerical expenditure amount.
   - **Category**: Select from master expense categories (e.g., *Site Travel, Hardware, Fooding, Consumables*).
   - **Location / Job Details**: Pre-filled with the parent job title; edit if necessary.
   - **Done By**: Select the field worker or technician who incurred the expense.
   - **Details**: Brief description of the expense.
   - **Tap "Log Expense"**: The expense is linked to both the task and parent job, added to the general ledger, and logged in the company audit trail.

---

### 7.6 Discussion, Comments & Voice Note Playback

The **History & Discussion** panel provides a complete chronological audit trail of the task:

1. **Automated Audit Logs**: Records whenever an assignee marks work as started, completed, or verified.
2. **Team Comments**: Read messages from managers, coordinators, and fellow workers.
3. **Listen to Voice Notes**:
   - Voice notes appear with a distinct green audio play icon: **`[ > Play Voice Note ]`**.
   - Tap the button to download and play the voice note using your phone's default media player.
4. **Post New Comments**:
   - Type your message into the text box.
   - Tap **Attach** to upload photos or documents.
   - Tap **Voice** to record a hands-free audio comment.
   - Tap **Post Comment** to transmit. All task assignees and observers receive an instant update.

---

## 8. Expense Ledger & Financial Position

The **Expenses** screen (accessible via the bottom navigation bar) provides a full financial ledger of petty cash, site expenses, and deposits:

```
+-------------------------------------------------------------+
| Expense Ledger                                              |
| Opening Balance: ₹ 2,500.00   |  Total Deposit: ₹ 5,000.00  |
| Total Expense:   ₹ 3,200.00   |  Closing Balance: ₹ 4,300.00|
+-------------------------------------------------------------+
| [Q Search category, location, details...             ] [Tune]|
+-------------------------------------------------------------+
| ( All ) [ Expense ] [ Deposit ] [ Category (v) ] [ Date (v) ]|
+-------------------------------------------------------------+
| [<-] Transportation                           - ₹ 450.00    |
|      22-11-2026 • Metro Phase 2 Site                        |
|      [Job / Task Link]  [User: John]  [Done by: Ramesh]     |
|                                                             |
| [->] Cash Received from HO                    + ₹ 2,000.00  |
|      21-11-2026 • Factory Cash Deposit                      |
+-------------------------------------------------------------+
|                  [ + Add Expense / Deposit ]                |
+-------------------------------------------------------------+
```

### 8.1 Ledger Summary & Cash Position
The summary card at the top calculates your exact cash position:
- **Opening Balance**: Net balance before the selected date filter period (`Prior Deposits - Prior Expenses`).
- **Total Deposit**: Money credited during the selected period.
- **Total Expense**: Money spent during the selected period.
- **Closing Balance**: Real-time cash in hand (`Opening + Deposits - Expenses`).

---

### 8.2 Searching & Date Range Filters

- **Search Bar**: Quickly find entries by category name, site location, comment details, or staff name.
- **Type Filter**: Filter between `All`, `Expense` (outgoing), and `Deposit` (incoming).
- **Category Filter**: Filter ledger entries by specific expense categories.
- **Date Presets & Custom Calendar Range**:
  - `All Time`: View full historical records.
  - `Today`: View today's transactions.
  - `Yesterday`: View yesterday's transactions.
  - `This Week`: View transactions from Monday through today.
  - `This Month`: View transactions for the current calendar month.
  - `Custom Range`: Opens a visual calendar date picker. Select any custom start and end date to review past project statements.

---

### 8.3 Logging Standalone Expenses & Deposits

Tap the floating **Add Expense / Deposit** button at the bottom of the ledger:

1. **Date**: Select transaction date.
2. **Amount**: Enter the exact monetary amount.
3. **Type**: Choose **Expense** (money spent) or **Deposit** (cash/bank transfer received).
4. **Category**: Select the relevant category from the master list.
5. **Incharge Allocation**: If you have supervisor permissions (`expense_add_for_all`), you can select another user as the incharge; otherwise it automatically attributes to you.
6. **Location**: Enter site name, factory, or vendor address.
7. **Done By Multi-Select**: Tap multiple worker chips to attribute shared fieldwork expenses.
8. **Expense Detail & Comments**: Enter invoice details, voucher numbers, or explanatory remarks.
9. **Tap Save**: Saves the record, recalculates ledger balances immediately, and syncs with the central server.

---

## 9. Notifications & Alerts

```
+-------------------------------------------------------------+
| Notifications                             [ Mark all read ] |
| 2 unread                                                    |
+-------------------------------------------------------------+
| • New Task Assigned: Main Entrance Signage                  |
|   You have been assigned a new task by Amit Sharma...       |
|   22 Nov 2026, 10:15 AM                                 [√] |
|                                                             |
|   Task Update: Ramesh marked task as In Progress            |
|   22 Nov 2026, 09:30 AM                                     |
+-------------------------------------------------------------+
```

1. **Push Alerts**: When your phone is locked or you are using another app, push notifications alert you to task assignments, status changes, new comments, and reminders.
2. **In-App Notification Banner**: If a notification arrives while using the app, an interactive snackbar displays the title and body with a **View** button.
3. **Notifications Screen**:
   - Access via the top-right Bell icon or Side Drawer.
   - Unread items feature a bright blue dot indicator and highlighted card border.
   - Tap any notification card or the checkmark icon to mark it as read.
   - Tap **Mark all read** in the header to dismiss all pending badges at once.

---

# PART II: DEVELOPER GUIDE

---

## 10. Architecture & Directory Structure

SignageFlow Mobile is built with Flutter and Dart, organized into a feature-first architectural layout:

```
mobile/
├── .env.example / .env              # Environment config (API_BASE_URL)
├── pubspec.yaml                     # Dependencies, assets & launcher icon config
├── analysis_options.yaml            # Dart static analysis lint rules
├── scripts/
│   ├── appbundle.sh                 # Standard build script
│   └── build_appbundle_release.sh   # Auto-incrementing version & release AAB builder
├── android/                         # Native Android project (Kotlin DSL, MinSDK 21)
│   ├── app/build.gradle.kts         # Package ID: com.tiwariindustries.signage
│   ├── app/google-services.json     # Firebase Android configuration
│   └── app/src/main/AndroidManifest.xml # Permissions: INTERNET, RECORD_AUDIO, NOTIFICATIONS
├── ios/                             # Native iOS project (Runner, CocoaPods)
│   ├── Runner/Info.plist            # NSMicrophoneUsageDescription & bundle keys
│   └── Runner/GoogleService-Info.plist # Firebase iOS configuration
└── lib/
    ├── main.dart                    # Application bootstrap, dotenv init, notification startup
    ├── app/
    │   └── signage_flow_app.dart    # MaterialApp definition, theme palette, auth router
    ├── core/
    │   ├── app_config.dart          # Secure storage instance, branding colors, API base URL
    │   ├── network/
    │   │   └── api_client.dart      # HTTP REST client, Bearer headers, multipart & download
    │   └── services/
    │       └── notification_service.dart # Firebase Messaging init, FCM token sync & streams
    ├── features/
    │   ├── auth/
    │   │   └── login_screen.dart    # Login form, Sanctum token exchange, offline checks
    │   ├── home/
    │   │   ├── app_shell.dart       # Main shell with AppBar, Drawer, Floating Nav & FCM SnackBar
    │   │   ├── home_dashboard_screen.dart # "Today Console" dashboard, metrics & quick actions
    │   │   └── notifications_screen.dart  # Paginated notifications list & read mutations
    │   ├── tasks/
    │   │   └── tasks_screen.dart    # Tasks list, TaskDetailScreen, complete modal, voice note
    │   └── expenses/
    │       └── expenses_screen.dart # Expense ledger, running balances, date filters, add sheet
    └── shared/
        ├── utils/
        │   └── formatters.dart      # Date parsing (d-m-Y H:i), INR currency formatting, status colors
        └── widgets/
            └── common_widgets.dart  # AppPill, AppAutocompleteField, AppPanel reusable components
```

---

## 11. State Management & Secure Storage

### Secure Session Storage
Tokens are stored using `FlutterSecureStorage` with hardware-backed encryption:
- Key: `'token'` stores the plain-text Laravel Sanctum personal access token.
- At application boot (`BootstrapScreen` in `signage_flow_app.dart`), the app reads `'token'` with a 3-second timeout safeguard. If valid, the user transitions directly to `TaskHome`; otherwise to `LoginScreen`.

### Reactivity & Lifecycle
- Screens use structured `StatefulWidget` instances with granular `setState()` mutations for predictable lifecycle behavior.
- All list screens implement `RefreshIndicator` connected to their primary asynchronous load routines.
- Bottom sheet modals receive callback functions (`onChanged`) to trigger background re-fetching on the parent view upon successful mutations.

---

## 12. Network Layer & API Client

All API requests pass through `ApiClient` (`lib/core/network/api_client.dart`):

```dart
class ApiClient {
  final String baseUrl;
  final String token;

  Uri uri(String path, [Map<String, String?> query = const {}]);
  Future<Map<String, dynamic>> getJson(String path, [Map<String, String?> query]);
  Future<Map<String, dynamic>> postJson(String path, Map<String, dynamic> body);
  Future<Map<String, dynamic>> multipart(String path, Map<String, String> fields, List<File> files);
  Future<void> downloadAndOpen(String url, String fileName);
  Future<void> logout();
}
```

### Key Architectural Behaviors
1. **Dynamic URL Resolution**: Automatically prepends `/api/mobile` to relative paths and cleans trailing slashes from `API_BASE_URL`.
2. **Standard Headers**: Automatically injects `Accept: application/json` and `Authorization: Bearer <token>`.
3. **Multipart File Transmission**: `multipart()` streams file attachments under the form key `files[]` alongside structured text fields.
4. **Direct Document Streaming**: `downloadAndOpen()` resolves the file URL against `baseUrl`, downloads the byte stream to the device's temporary cache directory (`getTemporaryDirectory()`), and invokes `OpenFilex.open()` to trigger the operating system's native viewer.
5. **Standardized Error Handling**: Intercepts `SocketException`, `ClientException`, and `FormatException` to present actionable user feedback (e.g., *"Cannot connect to server. Please check your internet connection."*).

---

## 13. Backend Laravel API Specification

All mobile endpoints are registered in `routes/api.php` under prefix `mobile` and route name `mobile.*`.

### 13.1 Authentication Endpoints

#### `POST /api/mobile/login`
- **Controller**: `App\Http\Controllers\Api\Mobile\AuthController@login`
- **Rate Limit**: 10 attempts per minute (`throttle:10,1`)
- **Request Body**:
  ```json
  {
    "email": "user@tiwariindustries.com",
    "password": "secretpassword"
  }
  ```
- **Permission Check**: User must possess either `task_MyTasksList` or `expense_list`.
- **Response `200 OK`**:
  ```json
  {
    "token": "12|aB3cD4eF...",
    "user": {
      "id": 14,
      "name": "Ramesh Kumar",
      "email": "ramesh@tiwariindustries.com",
      "permissions": {
        "expense_back_date_entry": false
      }
    }
  }
  ```

#### `POST /api/mobile/logout`
- **Controller**: `App\Http\Controllers\Api\Mobile\AuthController@logout`
- **Auth**: Bearer Sanctum Token
- **Action**: Deletes the current personal access token from `personal_access_tokens`.

---

### 13.2 Tasks Endpoints

#### `GET /api/mobile/tasks/meta`
- **Controller**: `App\Http\Controllers\Api\Mobile\TaskController@meta`
- **Auth**: `auth:sanctum` + permission `task_MyTasksList`
- **Response `200 OK`**:
  ```json
  {
    "enquiry_options": ["ENQ-2026-001", "ENQ-2026-002"],
    "sales_order_options": ["SO-2026-010", "SO-2026-011"],
    "expense_categories": ["Site Travel", "Fooding", "Hardware", "Fabrication"],
    "expense_done_by": ["Ramesh Kumar", "Suresh Patel", "Anil Sharma"]
  }
  ```

#### `GET /api/mobile/tasks`
- **Controller**: `App\Http\Controllers\Api\Mobile\TaskController@index`
- **Query Parameters**:
  - `status`: `all`, `pending`, `accepted`, `in_progress`, `completed`, `over_due`, `viewer`
  - `search`: String matching task `title` or `description`
  - `per_page`: Integer (default 25)
- **Scope**: Returns tasks where user is in `task_assignees` or `task_viewers`.

#### `GET /api/mobile/tasks/{id}`
- **Controller**: `App\Http\Controllers\Api\Mobile\TaskController@show`
- **Response `200 OK`**:
  Returns comprehensive task details including `task`, `my_status`, `assignees`, `viewers`, `files`, `comments` (with nested attachments), `job_files`, and `expenses`.

#### `POST /api/mobile/tasks/{id}/status`
- **Controller**: `App\Http\Controllers\Api\Mobile\TaskController@updateStatus`
- **Request Type**: Multipart Form Data
- **Fields**:
  - `status`: String (`accepted`, `in_progress`, `completed`, `verified`, `closed`)
  - `comment`: Optional text comment
  - `enquiry_no`: Optional string (mandatory if task has `need_enquiry_number = true` upon completion)
  - `sales_order_no`: Optional string (mandatory if task has `need_sales_order_number = true` upon completion)
  - `files[]`: Optional binary files (photos, PDFs, `.m4a` audio recordings)

#### `POST /api/mobile/tasks/{id}/comments`
- **Controller**: `App\Http\Controllers\Api\Mobile\TaskController@addComment`
- **Request Type**: Multipart Form Data
- **Fields**: `comment` (string), `files[]` (attachments)

#### `POST /api/mobile/tasks/{id}/expenses`
- **Controller**: `App\Http\Controllers\Api\Mobile\TaskController@storeExpense`
- **Fields**: `exp_date` (Y-m-d), `amount` (numeric), `amt_type` (`Expense`|`Deposit`), `exp_cate`, `job_details`, `details`, `doneby[]`, `job_no`

#### Attachment Download Routes
- `GET /api/mobile/tasks/files/{id}`: Downloads file from `TaskFile`.
- `GET /api/mobile/tasks/comment-files/{id}`: Downloads file from `TaskCommentFile`.
- `GET /api/mobile/tasks/job-files/{id}`: Downloads engineering drawings from `JobFile`.

---

### 13.3 Expense Endpoints

#### `GET /api/mobile/expenses/meta`
- **Controller**: `App\Http\Controllers\Api\Mobile\ExpenseController@meta`
- **Response**: Categories, worker options, incharges list, and user permission flags (`can_add_for_all`, `can_list_for_all`, `can_back_date_entry`).

#### `GET /api/mobile/expenses`
- **Controller**: `App\Http\Controllers\Api\Mobile\ExpenseController@index`
- **Query Parameters**: `search`, `type`, `category`, `date_from`, `date_to`, `all_dates`, `per_page`
- **Calculated Summary Block**:
  ```json
  {
    "summary": {
      "opening": 2500.00,
      "deposit": 5000.00,
      "expense": 3200.00,
      "closing": 4300.00
    },
    "expenses": { ...paginated data... }
  }
  ```

#### `POST /api/mobile/expenses`
- **Controller**: `App\Http\Controllers\Api\Mobile\ExpenseController@store`
- **Validation**: Enforces 2-day backdate limit unless user has `expense_back_date_entry`. Enforces `incharge = user.name` unless user has `expense_add_for_all`.

---

### 13.4 Push Token & Notifications Endpoints

#### `POST /api/mobile/fcm-token`
- **Controller**: `App\Http\Controllers\Api\Mobile\ExpenseController@storeFcmToken`
- **Payload**: `{"token": "fcm_token_string...", "platform": "android"}`
- **Action**: Upserts into `mobile_device_tokens` table for the authenticated user.

#### `GET /api/mobile/notifications`
- **Controller**: `App\Http\Controllers\Api\Mobile\NotificationController@index`
- **Response**: Paginated user notifications + `unread_count`.

#### `POST /api/mobile/notifications/{id}/read`
- **Controller**: `App\Http\Controllers\Api\Mobile\NotificationController@markAsRead`
- **Action**: Sets `read_at = now()` on the specified notification.

#### `POST /api/mobile/notifications/read-all`
- **Controller**: `App\Http\Controllers\Api\Mobile\NotificationController@markAllAsRead`
- **Action**: Sets `read_at = now()` on all unread notifications belonging to the user.

---

## 14. Business Logic, Workflows & Automation

### 14.1 Task Lifecycle & Multi-Assignee Aggregation

Tasks in SignageFlow support multiple assignees via the `task_assignees` pivot table (`status`, `completed_at`, `feedback`).
When an individual assignee marks their status as `completed`:
1. The user's pivot record is updated with `completed_at = now()`.
2. The server inspects the status of all other assignees on that task:
   - If **all** assignees have completed: Overall task status transitions to `completed`.
   - If any assignee is still working: Overall task status remains `in_progress`.
   - If all assignees are accepted: Task status remains `accepted`.

```
[ Task Created ] 
       |
       v
+--------------+        Scheduled Start Date elapsed?
|   Pending    | ------------------------------------+
+--------------+                                     |
       |                                             |
       v                                             v
+--------------+                              +---------------+
|   Accepted   | ---------------------------> |  In Progress  |
+--------------+                              +---------------+
                                                     |
                                                     v  (Attach photos, voice notes,
                                                     |   Enquiry & SO numbers)
                                              +---------------+
                                              |   Completed   |
                                              +---------------+
                                                     |
                                                     v  (Supervisor review)
                                              +---------------+
                                              | Verified/Close|
                                              +---------------+
```

---

### 14.2 Sequential Stage Auto-Start (`start_on_previous_complete`)

When jobs are broken down into sequential fabrication or installation stages (e.g., *1. Design -> 2. Laser Cutting -> 3. Assembly -> 4. Site Installation*):
- If Stage 1 completes, `TaskController` queries the subsequent task in the same job (`job_stage_sort_order + 1`).
- If the next task has `start_on_previous_complete = true` and `start_date` is null:
  1. The server immediately sets `nextTask.start_date = now()`.
  2. Dispatches `TaskNotificationService::notifyTaskStart($nextTask)`.
  3. Sends real-time FCM push notifications to all Stage 2 assignees alerting them that prerequisite work is finished and their task is ready to begin.

---

### 14.3 Job Completion Auto-Detection

When all stage tasks in a parent Job reach completion:
1. `task->job->recalculateStatus()` calculates overall completion.
2. If `job.status` becomes `completed` and `job_completed_notified` is false:
   - Sets `job_completed_notified = true`.
   - Fires `TaskNotificationService::notifyJobCompleted($job)` to email and alert project managers.

---

### 14.4 Two-Day Backdate Rule & Permissions

To prevent accounting discrepancies:
- Back-dating expense entries beyond 2 days (`strtotime($exp_date) < strtotime('-2 days')`) triggers a validation exception:
  *"The Expense Date cannot be older than 2 days."*
- **Bypass**: Only users granted the Spatie permission `expense_back_date_entry` can log historical expenses beyond this window.

---

### 14.5 Audio & Media Classification

When files are uploaded through comments or completion submissions:
- `TaskController::detectActivityType()` analyzes filenames and MIME types:
  - Audio formats (`.m4a`, `.mp3`, `.wav`, `.aac`, `.webm`, or `voice_note` prefix) are classified as `voice_note`.
  - Documents and images are classified as `file_upload`.
- This triggers distinct notification templates:
  - *"Ramesh Kumar uploaded a voice note on task 'Main Entrance Signage'"* with instant playback cues.

---

## 15. Push Notification Pipeline (FCM v1)

```
[ Task Event Occurs in Laravel ]
              |
              v
[ TaskNotificationService::send() ]
              |
              +---> Saves UserNotification (Database in-app history)
              |
              +---> Queries User's MobileDeviceToken rows
              |
              v
[ FirebaseService::sendPushNotification() ]
              |
              +---> Reads storage/app/firebase-service-account.json
              +---> Generates Google OAuth2 Bearer Token
              +---> POST https://fcm.googleapis.com/v1/projects/{id}/messages:send
              |
              v
[ Firebase Cloud Messaging Gateway ]
              |
              v
+-----------------------------+-----------------------------+
| Android Device              | iOS Device (APNs)           |
| (FCM Background Receiver)   | (Apple Push Notification)   |
+-----------------------------+-----------------------------+
              |
              v
   App Foreground?  ---> Displays Floating SnackBar with "View" action
   App Background?  ---> System Notification Tray Alert
```

- **Defensive Design**: If `firebase-service-account.json` is missing during local development, `FirebaseService` logs a warning placeholder without throwing exceptions, ensuring API tests run smoothly.

---

## 16. Build, Release & Versioning Procedures

### 16.1 Local Development Setup

1. **Configure Environment**:
   ```bash
   cd mobile
   cp .env.example .env
   # Set API_BASE_URL to your development or staging URL (without trailing slash):
   # API_BASE_URL=https://staging.tiwariindustries.com
   ```
2. **Install Flutter Dependencies**:
   ```bash
   flutter pub get
   ```
3. **Run on Connected Device or Simulator**:
   ```bash
   flutter run
   ```

---

### 16.2 Automated Version Bump & App Bundle Build

The repository includes an automated script that reads the version from `pubspec.yaml`, automatically increments the build number, updates the file, and compiles an optimized release Android App Bundle (`.aab`):

```bash
cd mobile
./scripts/build_appbundle_release.sh
```

**Script Mechanics (`build_appbundle_release.sh`)**:
- Parses `version: 1.0.0+3` in `pubspec.yaml`.
- Increments build number: `1.0.0+4`.
- Safely replaces the version line using `awk`.
- Executes `flutter build appbundle --release --build-number="4"`.
- Output bundle: `build/app/outputs/bundle/release/app-release.aab`.

---

### 16.3 Android Keystore & Code Signing

Android release signing is configured in `mobile/android/app/build.gradle.kts` and reads credentials from `mobile/android/key.properties`:

```properties
storePassword=your_keystore_password
keyPassword=your_key_password
keyAlias=upload
storeFile=/path/to/your/signage-keystore.jks
```

To build a standalone signed release APK:
```bash
flutter build apk --release
```

---

### 16.4 iOS Deployment Configuration

1. **Install CocoaPods**:
   ```bash
   cd mobile/ios
   pod install
   cd ..
   ```
2. **Permissions**:
   Ensure `mobile/ios/Runner/Info.plist` maintains the microphone usage declaration:
   ```xml
   <key>NSMicrophoneUsageDescription</key>
   <string>SignageFlow records voice notes for task comments.</string>
   ```
3. **Build Archive**:
   ```bash
   flutter build ipa --release
   ```
4. Open `mobile/ios/Runner.xcworkspace` in Xcode to distribute to Apple TestFlight or the App Store.

---

### 16.5 App Icon Generation

Launcher icons for Android and iOS are defined under `flutter_launcher_icons` in `pubspec.yaml`:
```yaml
flutter_launcher_icons:
  android: true
  ios: true
  image_path: "assets/logo.png"
  adaptive_icon_background: "#ffffff"
  adaptive_icon_foreground: "assets/logo.png"
  min_sdk_android: 21
  remove_alpha_ios: true
```

Whenever branding assets in `mobile/assets/` are updated, regenerate icons with:
```bash
dart run flutter_launcher_icons
```

---

## 17. Common Troubleshooting & Diagnostics

| Symptom | Probable Cause | Resolution |
|---------|----------------|------------|
| Screen shows *"API_BASE_URL is missing"* | `.env` file is missing or `API_BASE_URL` is empty | Copy `mobile/.env.example` to `mobile/.env` and specify your domain without a trailing slash. |
| Login error: *"You do not have mobile app access."* | User lacks required Spatie permissions | Grant either `task_MyTasksList` or `expense_list` to the user's role in SignageFlow Admin. |
| Cannot start task: *"You cannot accept or start this task before [date]"* | Task has a scheduled future start date | The task is locked by business rules until the scheduled timestamp arrives. |
| Complete task fails: *"Enquiry Number is required"* | Task has `need_enquiry_number` enabled | Select a valid Enquiry Number from the autocomplete dropdown prior to submitting. |
| Expense save fails: *"The Expense Date cannot be older than 2 days"* | User attempted back-dating beyond 2 days | Change date to within the last 48 hours or assign the `expense_back_date_entry` permission. |
| Voice Note button shows *"Microphone permission is required"* | OS microphone permission denied | Open device settings -> Applications -> SignageFlow -> Permissions -> Enable Microphone. |
| Push notifications not received | Missing FCM registration or device token | Verify `google-services.json` (Android) and `GoogleService-Info.plist` (iOS) are present in native folders. Verify user appears in `mobile_device_tokens`. |
| Server offline error | Domain unreachable or SSL certificate invalid | Check server status, ensure backend is running, and verify internet connectivity. |
