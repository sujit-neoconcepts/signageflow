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
  });
}
