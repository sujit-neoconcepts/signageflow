import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/app_config.dart';
import '../../core/network/api_client.dart';
import '../../core/services/notification_service.dart';
import '../auth/login_screen.dart';
import '../expenses/expenses_screen.dart';
import '../tasks/tasks_screen.dart';
import 'home_dashboard_screen.dart';
import 'notifications_screen.dart';

class TaskHome extends StatefulWidget {
  const TaskHome({super.key, required this.api});

  final ApiClient api;

  @override
  State<TaskHome> createState() => _TaskHomeState();
}

class _TaskHomeState extends State<TaskHome> {
  int _index = 0;
  int _unreadCount = 0;
  StreamSubscription? _fcmSubscription;

  @override
  void initState() {
    super.initState();
    _fetchUnreadCount();

    _fcmSubscription = NotificationService.instance.onMessage.listen((message) {
      _fetchUnreadCount();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  message.notification?.title ?? 'New Notification',
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
                if (message.notification?.body != null)
                  Text(message.notification!.body!),
              ],
            ),
            duration: const Duration(seconds: 10),
            action: SnackBarAction(
              label: 'View',
              onPressed: () async {
                await Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => NotificationsScreen(api: widget.api),
                  ),
                );
                _fetchUnreadCount();
              },
            ),
          ),
        );
      }
    });
  }

  @override
  void dispose() {
    _fcmSubscription?.cancel();
    super.dispose();
  }

  Future<void> _fetchUnreadCount() async {
    try {
      final data = await widget.api.getJson('/notifications');
      if (mounted) {
        setState(() {
          _unreadCount = data['unread_count'] as int? ?? 0;
        });
      }
    } catch (e) {
      debugPrint('Failed to load unread count: $e');
    }
  }

  Future<void> _logout() async {
    await widget.api.logout();
    if (!mounted) return;
    Navigator.of(
      context,
    ).pushReplacement(MaterialPageRoute(builder: (_) => const LoginScreen()));
  }

  @override
  Widget build(BuildContext context) {
    final pages = [
      HomeDashboardScreen(
        api: widget.api,
        onOpenTasks: () => setState(() => _index = 1),
        onOpenExpenses: () => setState(() => _index = 2),
      ),
      TasksScreen(api: widget.api),
      ExpensesScreen(api: widget.api),
    ];
    final titles = ['Home', 'Tasks', 'Expenses'];

    return Scaffold(
      extendBody: true,
      appBar: AppBar(
        titleSpacing: 0,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'SignageFlow',
              style: TextStyle(fontWeight: FontWeight.w900),
            ),
            Text(
              titles[_index],
              style: TextStyle(
                fontSize: 12,
                color: Colors.blueGrey.shade600,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
        leading: Builder(
          builder: (context) => IconButton(
            icon: Container(
              padding: const EdgeInsets.all(0),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(8),
                border: Border.all(
                  color: const Color(0xFFE2E8F0),
                  width: 1.5,
                ),
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(6),
                child: Image.asset(
                  'assets/images/logo-w.png',
                  height: 32,
                  width: 32,
                  fit: BoxFit.cover,
                  errorBuilder: (context, error, stackTrace) => const Icon(
                    Icons.menu_rounded,
                    color: Colors.white,
                    size: 26,
                  ),
                ),
              ),
            ),
            onPressed: () => Scaffold.of(context).openDrawer(),
          ),
        ),
        actions: [
          Stack(
            alignment: Alignment.center,
            children: [
              IconButton(
                onPressed: () async {
                  await Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => NotificationsScreen(api: widget.api),
                    ),
                  );
                  _fetchUnreadCount();
                },
                icon: const Icon(Icons.notifications_none_rounded),
                tooltip: 'View notifications',
              ),
              if (_unreadCount > 0)
                Positioned(
                  right: 8,
                  top: 8,
                  child: Container(
                    padding: const EdgeInsets.all(2),
                    decoration: BoxDecoration(
                      color: Colors.red,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    constraints: const BoxConstraints(
                      minWidth: 16,
                      minHeight: 16,
                    ),
                    child: Text(
                      '$_unreadCount',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(width: 6),
        ],
      ),
      drawer: AppDrawer(
        currentIndex: _index,
        onSelect: (index) {
          Navigator.pop(context);
          setState(() => _index = index);
        },
        onLogout: _logout,
        api: widget.api,
      ),
      body: SafeArea(
        bottom: false,
        child: IndexedStack(index: _index, children: pages),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Container(
          margin: const EdgeInsets.fromLTRB(18, 0, 18, 14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
            border: Border.all(
              color: const Color(0xFFE2E8F0),
              width: 1,
            ),
            boxShadow: const [
              BoxShadow(
                color: Color(0x0E000000),
                blurRadius: 18,
                offset: Offset(0, -4), // project shadow upwards to separate from body content
              ),
              BoxShadow(
                color: Color(0x06000000),
                blurRadius: 8,
                offset: Offset(0, 4),
              ),
            ],
          ),
          child: NavigationBar(
            selectedIndex: _index,
            backgroundColor: Colors.transparent,
            indicatorColor: appAccent.withValues(alpha: .14),
            onDestinationSelected: (index) => setState(() => _index = index),
            destinations: const [
              NavigationDestination(
                icon: Icon(Icons.space_dashboard_outlined),
                selectedIcon: Icon(Icons.space_dashboard_rounded),
                label: 'Home',
              ),
              NavigationDestination(
                icon: Icon(Icons.task_alt_outlined),
                selectedIcon: Icon(Icons.task_alt_rounded),
                label: 'Tasks',
              ),
              NavigationDestination(
                icon: Icon(Icons.receipt_long_outlined),
                selectedIcon: Icon(Icons.receipt_long_rounded),
                label: 'Expenses',
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class AppDrawer extends StatelessWidget {
  const AppDrawer({
    super.key,
    required this.currentIndex,
    required this.onSelect,
    required this.onLogout,
    required this.api,
  });

  final int currentIndex;
  final ValueChanged<int> onSelect;
  final VoidCallback onLogout;
  final ApiClient api;

  @override
  Widget build(BuildContext context) {
    return Drawer(
      child: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(22, 58, 22, 26),
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [appInk, Color(0xFF0E7490)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Image.asset(
                  'assets/images/logo-b.png',
                  height: 54,
                  errorBuilder: (context, error, stackTrace) => const Icon(
                    Icons.business_rounded,
                    color: Colors.white,
                    size: 48,
                  ),
                ),
                const SizedBox(height: 18),
                const Text(
                  'SignageFlow',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 24,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Tiwari Industries',
                  style: TextStyle(
                    color: Colors.white70,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          _DrawerTile(
            index: 0,
            currentIndex: currentIndex,
            icon: Icons.space_dashboard_rounded,
            title: 'Home',
            onTap: onSelect,
          ),
          _DrawerTile(
            index: 1,
            currentIndex: currentIndex,
            icon: Icons.task_alt_rounded,
            title: 'My Tasks',
            onTap: onSelect,
          ),
          _DrawerTile(
            index: 2,
            currentIndex: currentIndex,
            icon: Icons.receipt_long_rounded,
            title: 'Expenses',
            onTap: onSelect,
          ),
          const Divider(height: 28, indent: 20, endIndent: 20),
          ListTile(
            leading: const Icon(Icons.notifications_active_rounded),
            title: const Text('Notifications'),
            onTap: () {
              Navigator.pop(context);
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => NotificationsScreen(api: api),
                ),
              );
            },
          ),
          const Spacer(),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                leading: const Icon(Icons.logout_rounded, color: Colors.red),
                title: const Text(
                  'Logout',
                  style: TextStyle(color: Colors.red, fontWeight: FontWeight.w800),
                ),
                onTap: onLogout,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _DrawerTile extends StatelessWidget {
  const _DrawerTile({
    required this.index,
    required this.currentIndex,
    required this.icon,
    required this.title,
    required this.onTap,
  });

  final int index;
  final int currentIndex;
  final IconData icon;
  final String title;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    final selected = index == currentIndex;
    return ListTile(
      selected: selected,
      selectedTileColor: appAccent.withValues(alpha: .1),
      leading: Icon(icon, color: selected ? appAccent : Colors.blueGrey),
      title: Text(
        title,
        style: TextStyle(
          fontWeight: selected ? FontWeight.w900 : FontWeight.w700,
        ),
      ),
      onTap: () => onTap(index),
    );
  }
}
