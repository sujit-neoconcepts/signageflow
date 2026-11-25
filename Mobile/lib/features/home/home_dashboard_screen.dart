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
  State<HomeDashboardScreen> createState() => HomeDashboardScreenState();
}

class HomeDashboardScreenState extends State<HomeDashboardScreen> {
  int _taskCount = 0;
  int _activeTaskCount = 0;
  int _overdueTaskCount = 0;
  Map<String, dynamic> _expenseSummary = {};
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> refresh() => _load();

  Future<void> _load() async {
    if (!mounted) return;
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

      final today = DateTime(DateTime.now().year, DateTime.now().month, DateTime.now().day);
      if (!mounted) return;
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
        _overdueTaskCount = taskRows
            .where(
              (task) =>
                  task['my_status'] != 'viewer' &&
                  ![
                    'completed',
                    'verified',
                    'closed',
                  ].contains(task['my_status']) &&
                  task['due_date'] != null &&
                  task['due_date'].toString().isNotEmpty &&
                  parseDMY(task['due_date'].toString()) != null &&
                  parseDMY(task['due_date'].toString())!.isBefore(today),
            )
            .length;
        _expenseSummary = (expenses['summary'] as Map<String, dynamic>?) ?? {};
      });
    } catch (e) {
      if (!mounted) return;
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
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(24),
              gradient: const LinearGradient(
                colors: [appInk, Color(0xFF0E7490)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x290EA5E9),
                  blurRadius: 20,
                  offset: Offset(0, 10),
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
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                          letterSpacing: -0.3,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Tasks, expenses, and field updates in one mobile workspace.',
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: .82),
                          fontSize: 13,
                          height: 1.35,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 14),
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: .94),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Image.asset(
                    'assets/images/logo-w.png',
                    width: 48,
                    height: 48,
                    fit: BoxFit.contain,
                    errorBuilder: (context, error, stackTrace) => const Icon(
                      Icons.business_rounded,
                      color: appInk,
                      size: 36,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          if (_loading)
            const Padding(
              padding: EdgeInsets.only(bottom: 10),
              child: LinearProgressIndicator(borderRadius: BorderRadius.all(Radius.circular(4))),
            ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Text(
                _error!,
                style: const TextStyle(
                  color: Colors.red,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          _SectionHeader(
            title: 'Tasks Overview',
            actionLabel: 'View all',
            onAction: widget.onOpenTasks,
          ),
          const SizedBox(height: 8),
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(
                  child: _CompactTaskMetricCard(
                    label: 'Active Tasks',
                    shortLabel: 'Active',
                    value: '$_activeTaskCount',
                    icon: Icons.flash_on_rounded,
                    color: Colors.deepOrange,
                    onTap: widget.onOpenTasks,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _CompactTaskMetricCard(
                    label: 'Overdue Tasks',
                    shortLabel: 'Overdue',
                    value: '$_overdueTaskCount',
                    icon: Icons.error_outline_rounded,
                    color: Colors.red,
                    highlight: _overdueTaskCount > 0,
                    onTap: widget.onOpenTasks,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _CompactTaskMetricCard(
                    label: 'Total Tasks',
                    shortLabel: 'Total',
                    value: '$_taskCount',
                    icon: Icons.task_alt_rounded,
                    color: appAccent,
                    onTap: widget.onOpenTasks,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          _SectionHeader(
            title: "Today's Cashflow",
            actionLabel: 'Open ledger',
            onAction: widget.onOpenExpenses,
          ),
          const SizedBox(height: 8),
          _BalanceBannerCard(
            label: 'Closing Balance',
            caption: 'Current cash position',
            value: moneyLabel(_expenseSummary['closing']),
            onTap: widget.onOpenExpenses,
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _HorizontalMetricCard(
                  label: 'Total Deposit',
                  value: moneyLabel(_expenseSummary['deposit']),
                  icon: Icons.trending_up_rounded,
                  color: Colors.teal,
                  onTap: widget.onOpenExpenses,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _HorizontalMetricCard(
                  label: 'Total Expense',
                  value: moneyLabel(_expenseSummary['expense']),
                  icon: Icons.trending_down_rounded,
                  color: Colors.redAccent,
                  onTap: widget.onOpenExpenses,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
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

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({
    required this.title,
    required this.actionLabel,
    required this.onAction,
  });

  final String title;
  final String actionLabel;
  final VoidCallback onAction;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w900,
              color: appInk,
            ),
          ),
        ),
        InkWell(
          borderRadius: BorderRadius.circular(8),
          onTap: onAction,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  actionLabel,
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                    color: appAccent,
                  ),
                ),
                const SizedBox(width: 2),
                const Icon(
                  Icons.arrow_forward_ios_rounded,
                  size: 12,
                  color: appAccent,
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _CompactTaskMetricCard extends StatelessWidget {
  const _CompactTaskMetricCard({
    required this.label,
    required this.shortLabel,
    required this.value,
    required this.icon,
    required this.color,
    required this.onTap,
    this.highlight = false,
  });

  final String label;
  final String shortLabel;
  final String value;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(18),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
        decoration: BoxDecoration(
          color: highlight ? const Color(0xFFFEF2F2) : Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(
            color: highlight ? Colors.red.shade200 : const Color(0xFFE2E8F0),
          ),
        ),
        child: Semantics(
          label: label,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Container(
                    width: 34,
                    height: 34,
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: .12),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(icon, color: color, size: 18),
                  ),
                  Flexible(
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(
                        value,
                        style: TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                          color: highlight ? Colors.red.shade700 : appInk,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                shortLabel,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                  color: appInk,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _BalanceBannerCard extends StatelessWidget {
  const _BalanceBannerCard({
    required this.label,
    required this.caption,
    required this.value,
    required this.onTap,
  });

  final String label;
  final String caption;
  final String value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(18),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: Colors.blue.withValues(alpha: .12),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Icon(
                Icons.account_balance_wallet_rounded,
                color: Colors.blue,
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    style: const TextStyle(
                      fontWeight: FontWeight.w900,
                      fontSize: 14,
                      color: appInk,
                    ),
                  ),
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
            FittedBox(
              fit: BoxFit.scaleDown,
              child: Text(
                value,
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                  color: appInk,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _HorizontalMetricCard extends StatelessWidget {
  const _HorizontalMetricCard({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
    required this.onTap,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(18),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 32,
                  height: 32,
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: .12),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(icon, color: color, size: 18),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: Colors.blueGrey.shade600,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            FittedBox(
              fit: BoxFit.scaleDown,
              child: Text(
                value,
                style: const TextStyle(
                  fontSize: 19,
                  fontWeight: FontWeight.w900,
                  color: appInk,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
