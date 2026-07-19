import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/app_config.dart';
import '../../core/network/api_client.dart';
import '../../shared/utils/formatters.dart';
import '../../shared/widgets/common_widgets.dart';

class ExpensesScreen extends StatefulWidget {
  const ExpensesScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<ExpensesScreen> createState() => _ExpensesScreenState();
}

class _ExpensesScreenState extends State<ExpensesScreen> {
  final _search = TextEditingController();
  List<dynamic> _expenses = [];
  Map<String, dynamic> _summary = {};
  Map<String, dynamic> _meta = {};
  String _type = 'all';
  String? _category;
  String _dateFilter = 'all';
  DateTimeRange? _customDateRange;
  bool _loading = true;
  String? _error;
  int _page = 1;
  int _lastPage = 1;
  bool _loadingMore = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _page = 1;
      _loading = true;
      _error = null;
    });
    try {
      final queryParams = <String, String>{
        'search': _search.text.trim(),
        'page': '$_page',
      };
      if (_type != 'all') {
        queryParams['type'] = _type;
      }
      if (_category != null) {
        queryParams['category'] = _category!;
      }

      if (_dateFilter == 'all') {
        queryParams['all_dates'] = '1';
      } else {
        DateTime? from;
        DateTime? to;
        final now = DateTime.now();

        if (_dateFilter == 'today') {
          from = DateTime(now.year, now.month, now.day);
          to = DateTime(now.year, now.month, now.day, 23, 59, 59);
        } else if (_dateFilter == 'yesterday') {
          final yesterday = now.subtract(const Duration(days: 1));
          from = DateTime(yesterday.year, yesterday.month, yesterday.day);
          to = DateTime(yesterday.year, yesterday.month, yesterday.day, 23, 59, 59);
        } else if (_dateFilter == 'this_week') {
          final startOfWeek = now.subtract(Duration(days: now.weekday - 1));
          from = DateTime(startOfWeek.year, startOfWeek.month, startOfWeek.day);
          to = DateTime(now.year, now.month, now.day, 23, 59, 59);
        } else if (_dateFilter == 'this_month') {
          from = DateTime(now.year, now.month, 1);
          to = DateTime(now.year, now.month, now.day, 23, 59, 59);
        } else if (_dateFilter == 'custom' && _customDateRange != null) {
          from = _customDateRange!.start;
          to = _customDateRange!.end;
        }

        if (from != null && to != null) {
          queryParams['date_from'] = from.toIso8601String().substring(0, 10);
          queryParams['date_to'] = to.toIso8601String().substring(0, 10);
        }
      }

      final results = await Future.wait([
        widget.api.getJson('/expenses', queryParams),
        if (_meta.isEmpty) widget.api.getJson('/expenses/meta'),
      ]);
      final data = results.first;
      setState(() {
        _summary = data['summary'] as Map<String, dynamic>;
        _expenses = List.from(
          ((data['expenses'] as Map<String, dynamic>)['data']
              as List<dynamic>),
        );
        _lastPage = (data['expenses'] as Map<String, dynamic>)['last_page'] as int? ?? 1;
        if (results.length > 1) _meta = results[1];
      });
    } catch (e) {
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadMore() async {
    if (_loading || _loadingMore || _page >= _lastPage) return;
    setState(() {
      _loadingMore = true;
    });
    try {
      final queryParams = <String, String>{
        'search': _search.text.trim(),
        'page': '${_page + 1}',
      };
      if (_type != 'all') {
        queryParams['type'] = _type;
      }
      if (_category != null) {
        queryParams['category'] = _category!;
      }

      if (_dateFilter == 'all') {
        queryParams['all_dates'] = '1';
      } else {
        DateTime? from;
        DateTime? to;
        final now = DateTime.now();

        if (_dateFilter == 'today') {
          from = DateTime(now.year, now.month, now.day);
          to = DateTime(now.year, now.month, now.day, 23, 59, 59);
        } else if (_dateFilter == 'yesterday') {
          final yesterday = now.subtract(const Duration(days: 1));
          from = DateTime(yesterday.year, yesterday.month, yesterday.day);
          to = DateTime(yesterday.year, yesterday.month, yesterday.day, 23, 59, 59);
        } else if (_dateFilter == 'this_week') {
          final startOfWeek = now.subtract(Duration(days: now.weekday - 1));
          from = DateTime(startOfWeek.year, startOfWeek.month, startOfWeek.day);
          to = DateTime(now.year, now.month, now.day, 23, 59, 59);
        } else if (_dateFilter == 'this_month') {
          from = DateTime(now.year, now.month, 1);
          to = DateTime(now.year, now.month, now.day, 23, 59, 59);
        } else if (_dateFilter == 'custom' && _customDateRange != null) {
          from = _customDateRange!.start;
          to = _customDateRange!.end;
        }

        if (from != null && to != null) {
          queryParams['date_from'] = from.toIso8601String().substring(0, 10);
          queryParams['date_to'] = to.toIso8601String().substring(0, 10);
        }
      }

      final data = await widget.api.getJson('/expenses', queryParams);
      setState(() {
        _page++;
        _expenses.addAll(
          ((data['expenses'] as Map<String, dynamic>)['data'] as List<dynamic>),
        );
        _lastPage = (data['expenses'] as Map<String, dynamic>)['last_page'] as int? ?? 1;
      });
    } catch (e) {
      _snack('Failed to load more: ${e.toString().replaceFirst('Exception: ', '')}', error: true);
    } finally {
      if (mounted) {
        setState(() {
          _loadingMore = false;
        });
      }
    }
  }

  Future<void> _openCreate() async {
    if (_meta.isEmpty) {
      try {
        _meta = await widget.api.getJson('/expenses/meta');
      } catch (e) {
        _snack(e.toString().replaceFirst('Exception: ', ''), error: true);
        return;
      }
    }
    if (!mounted) return;
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (_) => StandaloneExpenseSheet(
        api: widget.api,
        meta: _meta,
        onChanged: _load,
        snack: _snack,
      ),
    );
  }

  void _snack(String message, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: error ? Colors.red : appInk,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final categories = (_meta['categories'] as List?)?.cast<String>() ?? [];
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: EdgeInsets.fromLTRB(18, 8, 18, 110 + MediaQuery.of(context).padding.bottom),
        children: [
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Expense Ledger',
                  style: TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.w900,
                    color: appInk,
                  ),
                ),
                const SizedBox(height: 12),
                IntrinsicHeight(
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Expanded(
                        child: _SmallSummary(
                          label: 'Opening Balance',
                          value: moneyLabel(_summary['opening']),
                          color: Colors.blueGrey,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: _SmallSummary(
                          label: 'Total Deposit',
                          value: moneyLabel(_summary['deposit']),
                          color: Colors.teal,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 8),
                IntrinsicHeight(
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Expanded(
                        child: _SmallSummary(
                          label: 'Total Expense',
                          value: moneyLabel(_summary['expense']),
                          color: Colors.red,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: _SmallSummary(
                          label: 'Closing Balance',
                          value: moneyLabel(_summary['closing']),
                          color: appAccent,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _search,
            onSubmitted: (_) => _load(),
            decoration: InputDecoration(
              hintText: 'Search category, location, details',
              prefixIcon: const Icon(Icons.search_rounded),
              suffixIcon: IconButton(
                onPressed: _load,
                icon: const Icon(Icons.tune_rounded),
              ),
            ),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              ChoiceChip(
                label: const Text('All'),
                selected: _type == 'all',
                onSelected: (_) => setState(() {
                  _type = 'all';
                  _load();
                }),
              ),
              ChoiceChip(
                label: const Text('Expense'),
                selected: _type == 'Expense',
                onSelected: (_) => setState(() {
                  _type = 'Expense';
                  _load();
                }),
              ),
              ChoiceChip(
                label: const Text('Deposit'),
                selected: _type == 'Deposit',
                onSelected: (_) => setState(() {
                  _type = 'Deposit';
                  _load();
                }),
              ),
              if (categories.isNotEmpty)
                DropdownMenu<String>(
                  width: 160,
                  hintText: 'Category',
                  initialSelection: _category,
                  dropdownMenuEntries: categories
                      .map((cat) => DropdownMenuEntry(value: cat, label: cat))
                      .toList(),
                  onSelected: (value) {
                    setState(() => _category = value);
                    _load();
                  },
                ),
              DropdownMenu<String>(
                width: 160,
                hintText: 'Date Filter',
                initialSelection: _dateFilter,
                dropdownMenuEntries: const [
                  DropdownMenuEntry(value: 'all', label: 'All Time'),
                  DropdownMenuEntry(value: 'today', label: 'Today'),
                  DropdownMenuEntry(value: 'yesterday', label: 'Yesterday'),
                  DropdownMenuEntry(value: 'this_week', label: 'This Week'),
                  DropdownMenuEntry(value: 'this_month', label: 'This Month'),
                  DropdownMenuEntry(value: 'custom', label: 'Custom Range'),
                ],
                onSelected: (value) async {
                  if (value == 'custom') {
                    final picked = await showDateRangePicker(
                      context: context,
                      firstDate: DateTime(2000),
                      lastDate: DateTime.now().add(const Duration(days: 305)),
                      initialDateRange: _customDateRange,
                    );
                    if (picked != null) {
                      setState(() {
                        _dateFilter = 'custom';
                        _customDateRange = picked;
                      });
                      _load();
                    }
                  } else if (value != null) {
                    setState(() {
                      _dateFilter = value;
                      _customDateRange = null;
                    });
                    _load();
                  }
                },
              ),
              if (_category != null)
                ActionChip(
                  label: const Text('Clear category'),
                  avatar: const Icon(Icons.close_rounded, size: 16),
                  onPressed: () {
                    setState(() => _category = null);
                    _load();
                  },
                ),
              if (_dateFilter == 'custom' && _customDateRange != null)
                ActionChip(
                  label: Text(
                    '${DateFormat('dd-MM').format(_customDateRange!.start)} - ${DateFormat('dd-MM-yy').format(_customDateRange!.end)}',
                  ),
                  avatar: const Icon(Icons.calendar_month_rounded, size: 16),
                  onPressed: () async {
                    final picked = await showDateRangePicker(
                      context: context,
                      firstDate: DateTime(2000),
                      lastDate: DateTime.now().add(const Duration(days: 305)),
                      initialDateRange: _customDateRange,
                    );
                    if (picked != null) {
                      setState(() {
                        _customDateRange = picked;
                      });
                      _load();
                    }
                  },
                ),
            ],
          ),
          const SizedBox(height: 14),
          if (_loading) const LinearProgressIndicator(),
          if (_error != null)
            Text(
              _error!,
              style: const TextStyle(
                color: Colors.red,
                fontWeight: FontWeight.w800,
              ),
            ),
          if (!_loading && _error == null && _expenses.isEmpty)
            const Padding(
              padding: EdgeInsets.only(top: 40),
              child: Center(child: Text('No expenses found.')),
            ),
          ..._expenses.map(
            (item) => ExpenseLedgerCard(expense: item as Map<String, dynamic>),
          ),
          if (_page < _lastPage) ...[
            const SizedBox(height: 14),
            Center(
              child: _loadingMore
                  ? const CircularProgressIndicator()
                  : OutlinedButton.icon(
                      onPressed: _loadMore,
                      icon: const Icon(Icons.arrow_downward_rounded),
                      label: const Text('Load More'),
                    ),
            ),
          ],
          const SizedBox(height: 30),
          FilledButton.icon(
            onPressed: _openCreate,
            icon: const Icon(Icons.add_rounded),
            label: const Text('Add Expense / Deposit'),
          ),
        ],
      ),
    );
  }
}

class _SmallSummary extends StatelessWidget {
  const _SmallSummary({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .09),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: TextStyle(
              color: color,
              fontWeight: FontWeight.w900,
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 4),
          FittedBox(
            child: Text(
              value,
              style: TextStyle(
                color: color,
                fontWeight: FontWeight.w900,
                fontSize: 16,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class ExpenseLedgerCard extends StatelessWidget {
  const ExpenseLedgerCard({super.key, required this.expense});

  final Map<String, dynamic> expense;

  @override
  Widget build(BuildContext context) {
    final isDeposit = expense['amt_type'] == 'Deposit';
    final color = isDeposit ? Colors.teal : Colors.red;
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CircleAvatar(
            backgroundColor: color.withValues(alpha: .1),
            child: Icon(
              isDeposit ? Icons.south_west_rounded : Icons.north_east_rounded,
              color: color,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        expense['exp_cate'] ?? '-',
                        style: const TextStyle(
                          fontWeight: FontWeight.w900,
                          color: appInk,
                        ),
                      ),
                    ),
                    Text(
                      moneyLabel(expense['signed_amount']),
                      style: TextStyle(
                        color: color,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  '${expense['exp_date']}  •  ${expense['job_details'] ?? '-'}',
                  style: TextStyle(
                    color: Colors.blueGrey.shade600,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if ((expense['details'] ?? '').toString().isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(
                      expense['details'],
                      style: TextStyle(color: Colors.blueGrey.shade700),
                    ),
                  ),
                if ((expense['job_task'] ?? '').toString().isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: AppPill(text: expense['job_task'], color: appAccent),
                  ),
                if ((expense['incharge'] ?? '').toString().isNotEmpty || (expense['doneby'] ?? '').toString().isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        if ((expense['incharge'] ?? '').toString().isNotEmpty)
                          _CardPill(
                            icon: Icons.person_rounded,
                            label: 'User: ${expense['incharge']}',
                          ),
                        if ((expense['doneby'] ?? '').toString().isNotEmpty)
                          _CardPill(
                            icon: Icons.engineering_rounded,
                            label: 'Done by: ${expense['doneby']}',
                          ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CardPill extends StatelessWidget {
  const _CardPill({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: Colors.blueGrey.shade50,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 12, color: Colors.blueGrey.shade700),
          const SizedBox(width: 4),
          Text(
            label,
            style: TextStyle(
              fontSize: 11,
              color: Colors.blueGrey.shade800,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class StandaloneExpenseSheet extends StatefulWidget {
  const StandaloneExpenseSheet({
    super.key,
    required this.api,
    required this.meta,
    required this.onChanged,
    required this.snack,
  });

  final ApiClient api;
  final Map<String, dynamic> meta;
  final Future<void> Function() onChanged;
  final void Function(String message, {bool error}) snack;

  @override
  State<StandaloneExpenseSheet> createState() => _StandaloneExpenseSheetState();
}

class _StandaloneExpenseSheetState extends State<StandaloneExpenseSheet> {
  final _amount = TextEditingController();
  final _location = TextEditingController();
  final _details = TextEditingController();
  final _comments = TextEditingController();
  String _type = 'Expense';
  String? _category;
  String? _incharge;
  DateTime _date = DateTime.now();
  final Set<String> _doneBy = {};
  bool _saving = false;

  Future<void> _submit() async {
    setState(() => _saving = true);
    try {
      await widget.api.postJson('/expenses', {
        'exp_date': DateFormat('yyyy-MM-dd').format(_date),
        'amount': _amount.text,
        'amt_type': _type,
        'exp_cate': _category,
        'job_details': _location.text,
        'details': _details.text,
        'job_no': _comments.text,
        'doneby': _doneBy.toList(),
        if (_incharge != null) 'incharge': _incharge,
      });
      widget.snack('Expense saved');
      await widget.onChanged();
      if (mounted) Navigator.pop(context);
    } catch (e) {
      widget.snack(e.toString().replaceFirst('Exception: ', ''), error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final categories =
        (widget.meta['categories'] as List?)?.cast<String>() ?? [];
    final incharges = (widget.meta['incharges'] as List?)?.cast<String>() ?? [];
    final canAddForAll = widget.meta['can_add_for_all'] == true;
    final doneByOptions = ((widget.meta['done_by'] as List?) ?? [])
        .map(
          (item) => item is Map
              ? (item['id'] ?? item['label']).toString()
              : item.toString(),
        )
        .where((item) => item.isNotEmpty)
        .toList();

    return Padding(
      padding: EdgeInsets.only(
        left: 18,
        right: 18,
        top: 18,
        bottom: MediaQuery.of(context).viewInsets.bottom + 18,
      ),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'New Expense / Deposit',
              style: TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.w900,
                color: appInk,
              ),
            ),
            const SizedBox(height: 14),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.calendar_today_rounded),
              title: const Text(
                'Date',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
              subtitle: Text(DateFormat('dd-MM-yyyy').format(_date)),
              onTap: () async {
                final picked = await showDatePicker(
                  context: context,
                  initialDate: _date,
                  firstDate: DateTime.now().subtract(const Duration(days: 365)),
                  lastDate: DateTime.now().add(const Duration(days: 30)),
                );
                if (picked != null) setState(() => _date = picked);
              },
            ),
            TextField(
              controller: _amount,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Amount'),
            ),
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(
              initialValue: _type,
              items: ['Expense', 'Deposit']
                  .map(
                    (value) =>
                        DropdownMenuItem(value: value, child: Text(value)),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _type = value!),
              decoration: const InputDecoration(labelText: 'Type'),
            ),
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(
              initialValue: _category,
              items: categories
                  .map(
                    (value) =>
                        DropdownMenuItem(value: value, child: Text(value)),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _category = value),
              decoration: const InputDecoration(labelText: 'Category'),
            ),
            const SizedBox(height: 10),
            if (canAddForAll && incharges.isNotEmpty) ...[
              DropdownButtonFormField<String>(
                initialValue: _incharge,
                items: incharges
                    .map(
                      (value) =>
                          DropdownMenuItem(value: value, child: Text(value)),
                    )
                    .toList(),
                onChanged: (value) => setState(() => _incharge = value),
                decoration: const InputDecoration(labelText: 'Incharge'),
              ),
              const SizedBox(height: 10),
            ],
            TextField(
              controller: _location,
              decoration: const InputDecoration(labelText: 'Location'),
            ),
            const SizedBox(height: 10),
            if (doneByOptions.isNotEmpty) ...[
              const Text(
                'Done By',
                style: TextStyle(fontWeight: FontWeight.w800, color: appInk),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: doneByOptions
                    .map(
                      (name) => FilterChip(
                        label: Text(name),
                        selected: _doneBy.contains(name),
                        onSelected: (selected) => setState(
                          () => selected
                              ? _doneBy.add(name)
                              : _doneBy.remove(name),
                        ),
                      ),
                    )
                    .toList(),
              ),
              const SizedBox(height: 10),
            ],
            TextField(
              controller: _details,
              minLines: 2,
              maxLines: 4,
              decoration: const InputDecoration(labelText: 'Expense Detail'),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _comments,
              minLines: 2,
              maxLines: 3,
              decoration: const InputDecoration(labelText: 'Comments'),
            ),
            const SizedBox(height: 14),
            FilledButton.icon(
              onPressed: _saving ? null : _submit,
              icon: const Icon(Icons.save_rounded),
              label: const Text('Save'),
            ),
          ],
        ),
      ),
    );
  }
}
