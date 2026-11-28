import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../../core/app_config.dart';
import '../../core/network/api_client.dart';
import '../../shared/widgets/common_widgets.dart';

class LeaveSelfServiceScreen extends StatefulWidget {
  const LeaveSelfServiceScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<LeaveSelfServiceScreen> createState() => LeaveSelfServiceScreenState();
}

class LeaveSelfServiceScreenState extends State<LeaveSelfServiceScreen> {
  bool _loading = true;
  String? _error;
  String _fy = '';
  List<dynamic> _balances = [];
  List<dynamic> _holidays = [];
  List<dynamic> _requests = [];

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
      final sumRes = await widget.api.getJson('/hrms/leave/summary');
      final reqRes = await widget.api.getJson('/hrms/leave/requests');
      if (!mounted) return;
      setState(() {
        _fy = sumRes['financial_year']?.toString() ?? '';
        _balances = (sumRes['balances'] as List<dynamic>?) ?? [];
        _holidays = (sumRes['holidays'] as List<dynamic>?) ?? [];
        _requests = (reqRes['requests'] as List<dynamic>?) ?? [];
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openApplySheet() async {
    if (_balances.isEmpty) return;
    int selectedTypeId = _balances.first['leave_type_id'] as int;
    String dayMode = 'full';
    final startCtrl = TextEditingController(
      text: DateTime.now().toIso8601String().substring(0, 10),
    );
    final endCtrl = TextEditingController(
      text: DateTime.now().toIso8601String().substring(0, 10),
    );
    final reasonCtrl = TextEditingController();
    File? attachment;

    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.fromLTRB(
          16,
          16,
          16,
          MediaQuery.of(ctx).viewInsets.bottom + 16,
        ),
        child: StatefulBuilder(
          builder: (ctx, setModalState) => Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Apply Leave (FY $_fy)',
                style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<int>(
                initialValue: selectedTypeId,
                items: _balances
                    .map(
                      (b) => DropdownMenuItem<int>(
                        value: b['leave_type_id'] as int,
                        child: Text('${b['name']} (Avail: ${b['available']})'),
                      ),
                    )
                    .toList(),
                onChanged: (v) => setModalState(() => selectedTypeId = v ?? selectedTypeId),
                decoration: const InputDecoration(labelText: 'Leave Type'),
              ),
              DropdownButtonFormField<String>(
                initialValue: dayMode,
                items: const [
                  DropdownMenuItem(value: 'full', child: Text('Full Day')),
                  DropdownMenuItem(value: 'first_half', child: Text('First Half')),
                  DropdownMenuItem(value: 'second_half', child: Text('Second Half')),
                ],
                onChanged: (v) => setModalState(() => dayMode = v ?? 'full'),
                decoration: const InputDecoration(labelText: 'Day Mode'),
              ),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: startCtrl,
                      decoration: const InputDecoration(labelText: 'Start (YYYY-MM-DD)'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextField(
                      controller: endCtrl,
                      decoration: const InputDecoration(labelText: 'End (YYYY-MM-DD)'),
                    ),
                  ),
                ],
              ),
              TextField(
                controller: reasonCtrl,
                decoration: const InputDecoration(labelText: 'Reason'),
              ),
              const SizedBox(height: 8),
              TextButton.icon(
                onPressed: () async {
                  final res = await FilePicker.platform.pickFiles();
                  if (res != null && res.files.single.path != null) {
                    setModalState(() => attachment = File(res.files.single.path!));
                  }
                },
                icon: const Icon(Icons.attach_file_rounded),
                label: Text(attachment == null ? 'Attach Document (Optional)' : 'Attached: ${attachment!.path.split('/').last}'),
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: () async {
                    try {
                      await widget.api.multipartSingleFile(
                        '/hrms/leave/requests',
                        {
                          'leave_type_id': selectedTypeId.toString(),
                          'start_date': startCtrl.text.trim(),
                          'end_date': endCtrl.text.trim(),
                          'day_mode': dayMode,
                          'reason': reasonCtrl.text.trim(),
                        },
                        'attachment',
                        attachment,
                      );
                      if (!ctx.mounted) return;
                      Navigator.pop(ctx);
                      _load();
                    } catch (e) {
                      if (!ctx.mounted) return;
                      ScaffoldMessenger.of(ctx).showSnackBar(
                        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
                      );
                    }
                  },
                  child: const Text('Submit Leave Request'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _cancelRequest(int id) async {
    try {
      await widget.api.postJson('/hrms/leave/requests/$id/cancel', {});
      await _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_loading) const LinearProgressIndicator(),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(_error!, style: const TextStyle(color: Colors.red)),
            ),
          AppPanel(
            title: 'Leave Balances (FY $_fy)',
            trailing: FilledButton.icon(
              onPressed: _openApplySheet,
              icon: const Icon(Icons.add_rounded, size: 18),
              label: const Text('Apply'),
            ),
            child: _balances.isEmpty
                ? const Text('No leave balances initialized.')
                : Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: _balances.map((b) {
                      return Container(
                        width: 145,
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF8FAFC),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: const Color(0xFFE2E8F0)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              b['code']?.toString() ?? '',
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                                color: appAccent,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '${b['available']}',
                              style: const TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            Text(
                              'Taken: ${b['taken']} • Pend: ${b['pending']}',
                              style: const TextStyle(fontSize: 11, color: Colors.blueGrey),
                            ),
                          ],
                        ),
                      );
                    }).toList(),
                  ),
          ),
          AppPanel(
            title: 'My Leave Requests',
            child: _requests.isEmpty
                ? const Text('No leave requests.')
                : Column(
                    children: _requests.map((r) {
                      final status = r['approval_status'].toString();
                      final isPending = status.startsWith('pending');
                      return ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text('${r['leave_type']} (${r['total_days']}d)'),
                        subtitle: Text('${r['start_date']} → ${r['end_date']}\n${r['reason']}'),
                        trailing: isPending
                            ? TextButton(
                                onPressed: () => _cancelRequest(r['id'] as int),
                                child: const Text('Cancel', style: TextStyle(color: Colors.red)),
                              )
                            : AppPill(
                                text: status.toUpperCase(),
                                color: status == 'approved' ? Colors.teal : Colors.red,
                              ),
                      );
                    }).toList(),
                  ),
          ),
          if (_holidays.isNotEmpty)
            AppPanel(
              title: 'FY Holidays',
              child: Column(
                children: _holidays
                    .map(
                      (h) => ListTile(
                        dense: true,
                        contentPadding: EdgeInsets.zero,
                        title: Text(h['name'].toString()),
                        subtitle: Text('${h['start_date']} (${h['holiday_type']})'),
                      ),
                    )
                    .toList(),
              ),
            ),
        ],
      ),
    );
  }
}
