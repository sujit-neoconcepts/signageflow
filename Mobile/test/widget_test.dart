import 'package:flutter_test/flutter_test.dart';
import 'package:flutter/material.dart';

import 'package:signageflow_mobile/core/network/api_client.dart';
import 'package:signageflow_mobile/features/auth/login_screen.dart';
import 'package:signageflow_mobile/features/home/app_shell.dart';

class _FakeApiClient extends ApiClient {
  _FakeApiClient() : super('https://example.com', 'test-token');

  int tasksCalls = 0;
  List<Map<String, dynamic>> taskRows = [
    {
      'id': 1,
      'title': 'Initial Task',
      'my_status': 'pending',
      'priority': 'medium',
      'due_date': '30-12-2099',
    },
  ];

  @override
  Future<Map<String, dynamic>> getJson(
    String path, [
    Map<String, String?> query = const {},
  ]) async {
    if (path == '/tasks') {
      tasksCalls++;
      return {
        'tasks': {'data': taskRows},
      };
    }
    if (path == '/tasks/meta') {
      return {
        'enquiry_options': [],
        'sales_order_options': [],
        'expense_categories': [],
        'expense_done_by': [],
      };
    }
    if (path == '/expenses') {
      return {
        'summary': {'deposit': 0, 'expense': 0, 'closing': 0},
        'expenses': {'data': [], 'last_page': 1},
      };
    }
    if (path == '/expenses/meta') {
      return {'categories': [], 'done_by': []};
    }
    if (path == '/notifications') {
      return {'unread_count': 0};
    }
    if (path == '/hrms/attendance/today') {
      return {
        'employee': {'id': 1, 'emp_code': 'E001', 'full_name': 'Test User', 'selfie_required': false},
        'shift': {'code': 'GS', 'name': 'General Shift', 'start_time': '09:30', 'end_time': '18:30'},
        'today_summary': {'status': 'not_punched'},
        'punches': [],
      };
    }
    if (path == '/hrms/attendance/history') {
      return {'days': []};
    }
    if (path == '/hrms/attendance/requests') {
      return {'requests': []};
    }
    if (path == '/hrms/leave/summary') {
      return {'financial_year': '2026-2027', 'balances': [], 'holidays': []};
    }
    if (path == '/hrms/leave/requests') {
      return {'requests': []};
    }
    if (path == '/hrms/payslips') {
      return {
        'data': [
          {
            'id': 1,
            'run_code': 'PR-M-STAFF-202611-ABCD',
            'period_label': 'Nov 2026',
            'period_start': '2026-11-01',
            'period_end': '2026-11-30',
            'payable_days': 30.0,
            'gross_earnings': 50000.0,
            'total_deductions': 5000.0,
            'net_wages': 45000.0,
            'download_url': '/hrms/payslips/1/download',
          }
        ]
      };
    }
    return {};
  }
}

void main() {
  testWidgets('renders login screen', (WidgetTester tester) async {
    await tester.pumpWidget(const MaterialApp(home: LoginScreen()));
    await tester.pump();

    expect(find.text('SignageFlow'), findsOneWidget);
    expect(find.text('Sign in securely'), findsOneWidget);
  });

  testWidgets('refreshes home and tasks screens on every bottom nav visit', (
    WidgetTester tester,
  ) async {
    final api = _FakeApiClient();

    await tester.pumpWidget(MaterialApp(home: TaskHome(api: api)));
    await tester.pumpAndSettle();

    // Initial load calls /tasks for Home and Tasks inside IndexedStack.
    final initialCalls = api.tasksCalls;
    expect(find.text('Tasks Overview'), findsOneWidget);

    // Simulate backend update: 1 completed + 1 newly assigned active task.
    api.taskRows = [
      {
        'id': 1,
        'title': 'Initial Task',
        'my_status': 'completed',
        'priority': 'medium',
        'due_date': '30-12-2099',
      },
      {
        'id': 2,
        'title': 'Newly Assigned Task',
        'my_status': 'pending',
        'priority': 'high',
        'due_date': '30-12-2099',
      },
    ];

    // Visit Tasks via bottom nav -> should fetch fresh tasks.
    await tester.tap(find.text('Tasks'));
    await tester.pumpAndSettle();
    expect(api.tasksCalls, initialCalls + 1);
    expect(find.text('Newly Assigned Task'), findsOneWidget);

    // Visit Home via bottom nav -> should fetch fresh tasks & counts.
    await tester.tap(find.text('Home'));
    await tester.pumpAndSettle();
    expect(api.tasksCalls, initialCalls + 2);

    // Visit HRMS via bottom nav -> should render Attendance self-service.
    await tester.tap(find.text('HRMS'));
    await tester.pumpAndSettle();
    expect(find.text('Today Clocking'), findsOneWidget);

    // Switch to Payslips tab
    await tester.tap(find.text('Payslips'));
    await tester.pumpAndSettle();
    expect(find.text('Nov 2026'), findsOneWidget);
    expect(find.text('₹45000.00'), findsOneWidget);
  });
}
