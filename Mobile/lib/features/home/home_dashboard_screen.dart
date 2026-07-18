import 'package:flutter/material.dart';

import '../../core/app_config.dart';
import '../../core/network/api_client.dart';
import '../../shared/utils/formatters.dart';
import '../../shared/widgets/common_widgets.dart';

class HomeDashboardScreen extends StatefulWidget {
  const HomeDashboardScreen({
    super.key,
    required this.api,
    required this.onOpenTasks,
    required this.onOpenExpenses,
  });

  final ApiClient api;
  final VoidCallback onOpenTasks;
  final VoidCallback onOpenExpenses;

  @override
  State<HomeDashboardScreen> createState() => _HomeDashboardScreenState();
}

class _HomeDashboardScreenState extends State<HomeDashboardScreen> {
  int _taskCount = 0;
  int _activeTaskCount = 0;
  Map<String, dynamic> _expenseSummary = {};
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final tasksResponse = await widget.api.getJson('/tasks', {
        'per_page': '100',
      });
      final taskRows =
          ((tasksResponse['tasks'] as Map<String, dynamic>)['data']
              as List<dynamic>);
      Map<String, dynamic> expenses = {};
      try {
        expenses = await widget.api.getJson('/expenses');
      } catch (_) {}

      setState(() {
        _taskCount = taskRows.length;
        _activeTaskCount = taskRows
            .where(
              (task) => ![
                'completed',
                'verified',
                'closed',
              ].contains(task['my_status']),
            )
            .length;
        _expenseSummary = (expenses['summary'] as Map<String, dynamic>?) ?? {};
      });
    } catch (e) {
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(18, 8, 18, 110),
        children: [
          Container(
            padding: const EdgeInsets.all(22),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(28),
              gradient: const LinearGradient(
                colors: [appInk, Color(0xFF0E7490)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x330EA5E9),
                  blurRadius: 26,
                  offset: Offset(0, 16),
                ),
              ],
            ),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Today Console',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 24,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Tasks, expenses, and field updates in one mobile workspace.',
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: .78),
                          height: 1.35,
                        ),
                      ),
                    ],
                  ),
                ),
                Image.asset(
                  'assets/images/logo-w.png',
                  width: 72,
                  errorBuilder: (context, error, stackTrace) => const Icon(
                    Icons.business_rounded,
                    color: Colors.white,
                    size: 52,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          if (_loading) const LinearProgressIndicator(),
          if (_error != null)
            Text(
              _error!,
              style: const TextStyle(
                color: Colors.red,
                fontWeight: FontWeight.w700,
              ),
            ),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            childAspectRatio: 1.15,
            children: [
              _ActionMetricCard(
                label: 'Tasks',
                value: '$_taskCount',
                caption: 'assigned or looped',
                icon: Icons.task_alt_rounded,
                color: appAccent,
                onTap: widget.onOpenTasks,
              ),
              _ActionMetricCard(
                label: 'Active',
                value: '$_activeTaskCount',
                caption: 'need attention',
                icon: Icons.flash_on_rounded,
                color: Colors.deepOrange,
                onTap: widget.onOpenTasks,
              ),
              _ActionMetricCard(
                label: 'Expense',
                value: moneyLabel(_expenseSummary['expense']),
                caption: 'selected period',
                icon: Icons.trending_down_rounded,
                color: Colors.red,
                onTap: widget.onOpenExpenses,
              ),
              _ActionMetricCard(
                label: 'Closing',
                value: moneyLabel(_expenseSummary['closing']),
                caption: 'cash position',
                icon: Icons.account_balance_wallet_rounded,
                color: Colors.teal,
                onTap: widget.onOpenExpenses,
              ),
            ],
          ),
          const SizedBox(height: 18),
          AppPanel(
            title: 'Quick Actions',
            child: Column(
              children: [
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const CircleAvatar(
                    backgroundColor: Color(0xFFE0F2FE),
                    child: Icon(Icons.task_rounded, color: appAccent),
                  ),
                  title: const Text(
                    'Open task work console',
                    style: TextStyle(fontWeight: FontWeight.w900),
                  ),
                  subtitle: const Text(
                    'Update status, comments, files, voice notes, task expenses',
                  ),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: widget.onOpenTasks,
                ),
                const Divider(),
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const CircleAvatar(
                    backgroundColor: Color(0xFFDCFCE7),
                    child: Icon(Icons.receipt_long_rounded, color: Colors.teal),
                  ),
                  title: const Text(
                    'Open expense ledger',
                    style: TextStyle(fontWeight: FontWeight.w900),
                  ),
                  subtitle: const Text(
                    'View standalone expenses and create entries',
                  ),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: widget.onOpenExpenses,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionMetricCard extends StatelessWidget {
  const _ActionMetricCard({
    required this.label,
    required this.value,
    required this.caption,
    required this.icon,
    required this.color,
    required this.onTap,
  });

  final String label;
  final String value;
  final String caption;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(22),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            CircleAvatar(
              backgroundColor: color.withValues(alpha: .12),
              child: Icon(icon, color: color),
            ),
            const Spacer(),
            FittedBox(
              child: Text(
                value,
                style: const TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w900,
                  color: appInk,
                ),
              ),
            ),
            Text(label, style: const TextStyle(fontWeight: FontWeight.w900)),
            Text(
              caption,
              style: TextStyle(
                color: Colors.blueGrey.shade500,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
