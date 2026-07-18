import 'package:flutter/material.dart';

import '../core/app_config.dart';
import '../core/network/api_client.dart';
import '../features/auth/login_screen.dart';
import '../features/home/app_shell.dart';

class SignageFlowApp extends StatelessWidget {
  const SignageFlowApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'SignageFlow',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: appAccent,
          brightness: Brightness.light,
        ),
        scaffoldBackgroundColor: const Color(0xFFF6F8FB),
        useMaterial3: true,
        inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: Colors.white,
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide.none,
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: appAccent, width: 1.5),
          ),
        ),
      ),
      home: const BootstrapScreen(),
    );
  }
}

class BootstrapScreen extends StatefulWidget {
  const BootstrapScreen({super.key});

  @override
  State<BootstrapScreen> createState() => _BootstrapScreenState();
}

class _BootstrapScreenState extends State<BootstrapScreen> {
  @override
  void initState() {
    super.initState();
    _boot();
  }

  Future<void> _boot() async {
    String? token;
    try {
      token = await appStorage
          .read(key: 'token')
          .timeout(const Duration(seconds: 3));
    } catch (error) {
      debugPrint('Stored login token could not be read: $error');
      try {
        await appStorage.delete(key: 'token');
      } catch (_) {}
    }

    if (!mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(
        builder: (_) {
          if (apiBaseUrl.isEmpty) {
            return const MissingConfigScreen();
          }

          return token == null
              ? const LoginScreen()
              : TaskHome(api: ApiClient(apiBaseUrl, token));
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(body: Center(child: CircularProgressIndicator()));
  }
}
