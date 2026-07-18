import 'package:flutter_test/flutter_test.dart';
import 'package:flutter/material.dart';

import 'package:signageflow_mobile/features/auth/login_screen.dart';

void main() {
  testWidgets('renders login screen', (WidgetTester tester) async {
    await tester.pumpWidget(const MaterialApp(home: LoginScreen()));
    await tester.pump();

    expect(find.text('SignageFlow'), findsOneWidget);
    expect(find.text('Sign in securely'), findsOneWidget);
  });
}
