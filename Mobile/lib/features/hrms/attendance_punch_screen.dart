import 'dart:io';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/app_config.dart';
import '../../core/network/api_client.dart';
import '../../shared/widgets/common_widgets.dart';

class AttendancePunchScreen extends StatefulWidget {
  const AttendancePunchScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<AttendancePunchScreen> createState() => AttendancePunchScreenState();
}

class AttendancePunchScreenState extends State<AttendancePunchScreen> {
  bool _loading = true;
  bool _punching = false;
  String? _error;
  Map<String, dynamic> _today = {};
  List<dynamic> _history = [];
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
      final todayRes = await widget.api.getJson('/hrms/attendance/today');
      final histRes = await widget.api.getJson('/hrms/attendance/history');
      final reqRes = await widget.api.getJson('/hrms/attendance/requests');
      if (!mounted) return;
      setState(() {
        _today = todayRes;
        _history = (histRes['days'] as List<dynamic>?) ?? [];
        _requests = (reqRes['requests'] as List<dynamic>?) ?? [];
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<Position> _resolvePosition() async {
    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      throw Exception('Location services are disabled. Please enable GPS.');
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      throw Exception('Location permission is required for GPS attendance.');
    }
    return Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
    );
  }

  Future<void> _punch(String direction) async {
    setState(() => _punching = true);
    try {
      final pos = await _resolvePosition();
      final emp = (_today['employee'] as Map<String, dynamic>?) ?? {};
      File? selfieFile;
      if (emp['selfie_required'] == true) {
        final picked = await ImagePicker().pickImage(
          source: ImageSource.camera,
          preferredCameraDevice: CameraDevice.front,
          imageQuality: 70,
        );
        if (picked == null) {
          throw Exception('Selfie capture is required for attendance.');
        }
        selfieFile = File(picked.path);
      }

      final res = await widget.api.multipartSingleFile(
        '/hrms/attendance/punch',
        {
          'direction': direction,
          'latitude': pos.latitude.toString(),
          'longitude': pos.longitude.toString(),
        },
        'selfie',
        selfieFile,
      );

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res['message']?.toString() ?? 'Punch saved')),
      );
      await _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.toString().replaceFirst('Exception: ', '')),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      if (mounted) setState(() => _punching = false);
    }
  }

  Future<void> _openRegularizationSheet() async {
    final dateCtrl = TextEditingController(
      text: DateTime.now().toIso8601String().substring(0, 10),
    );
    final inCtrl = TextEditingController();
    final outCtrl = TextEditingController();
    final reasonCtrl = TextEditingController();
    String reqType = 'time_correction';

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
              const Text(
                'Attendance Regularization',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: reqType,
                items: const [
                  DropdownMenuItem(value: 'time_correction', child: Text('Time Correction')),
                  DropdownMenuItem(value: 'missed_in', child: Text('Missed Punch In')),
                  DropdownMenuItem(value: 'missed_out', child: Text('Missed Punch Out')),
                  DropdownMenuItem(value: 'geofence_override', child: Text('Geofence Override')),
                ],
                onChanged: (v) => setModalState(() => reqType = v ?? 'time_correction'),
                decoration: const InputDecoration(labelText: 'Request Type'),
              ),
              TextField(
                controller: dateCtrl,
                decoration: const InputDecoration(labelText: 'Date (YYYY-MM-DD)'),
              ),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: inCtrl,
                      decoration: const InputDecoration(labelText: 'In (HH:MM)'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextField(
                      controller: outCtrl,
                      decoration: const InputDecoration(labelText: 'Out (HH:MM)'),
                    ),
                  ),
                ],
              ),
              TextField(
                controller: reasonCtrl,
                decoration: const InputDecoration(labelText: 'Reason'),
              ),
              const SizedBox(height: 14),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: () async {
                    try {
                      await widget.api.postJson('/hrms/attendance/regularize', {
                        'attendance_date': dateCtrl.text.trim(),
                        'request_type': reqType,
                        if (inCtrl.text.trim().isNotEmpty)
                          'requested_in_time': inCtrl.text.trim(),
                        if (outCtrl.text.trim().isNotEmpty)
                          'requested_out_time': outCtrl.text.trim(),
                        'reason': reasonCtrl.text.trim(),
                      });
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
                  child: const Text('Submit Request'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final shift = (_today['shift'] as Map<String, dynamic>?) ?? {};
    final summary = (_today['today_summary'] as Map<String, dynamic>?) ?? {};
    final punches = (_today['punches'] as List<dynamic>?) ?? [];

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
            title: 'Today Clocking',
            trailing: AppPill(
              text: (summary['status'] ?? 'not_punched').toString().toUpperCase(),
              color: appAccent,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (shift.isNotEmpty)
                  Text(
                    'Shift: ${shift['name']} (${shift['start_time']} - ${shift['end_time']})',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                const SizedBox(height: 6),
                Text(
                  'In: ${summary['first_in'] ?? '--:--'}  •  Out: ${summary['last_out'] ?? '--:--'}',
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: FilledButton.icon(
                        onPressed: _punching ? null : () => _punch('in'),
                        icon: const Icon(Icons.login_rounded),
                        label: const Text('Punch In'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: FilledButton.tonalIcon(
                        onPressed: _punching ? null : () => _punch('out'),
                        icon: const Icon(Icons.logout_rounded),
                        label: const Text('Punch Out'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                OutlinedButton.icon(
                  onPressed: _openRegularizationSheet,
                  icon: const Icon(Icons.edit_calendar_rounded),
                  label: const Text('Request Regularization'),
                ),
                if (punches.isNotEmpty) ...[
                  const Divider(height: 20),
                  ...punches.map(
                    (p) => Text(
                      '• ${p['direction'].toString().toUpperCase()} at ${p['punched_at']} (${p['channel']})'
                      '${p['is_out_of_geofence'] == true ? ' [Outside Geofence]' : ''}',
                      style: const TextStyle(fontSize: 12),
                    ),
                  ),
                ],
              ],
            ),
          ),
          AppPanel(
            title: 'Monthly Attendance Log',
            child: _history.isEmpty
                ? const Text('No attendance records for this month.')
                : Column(
                    children: _history.take(15).map((d) {
                      return ListTile(
                        dense: true,
                        contentPadding: EdgeInsets.zero,
                        title: Text('${d['date']}  —  ${d['status'].toString().toUpperCase()}'),
                        subtitle: Text('In: ${d['first_in'] ?? '-'} | Out: ${d['last_out'] ?? '-'} | Worked: ${d['worked_minutes']}m'),
                      );
                    }).toList(),
                  ),
          ),
          AppPanel(
            title: 'My Regularization Requests',
            child: _requests.isEmpty
                ? const Text('No regularization requests submitted.')
                : Column(
                    children: _requests.take(10).map((r) {
                      return ListTile(
                        dense: true,
                        contentPadding: EdgeInsets.zero,
                        title: Text('${r['attendance_date']} (${r['request_type']})'),
                        subtitle: Text(r['reason']?.toString() ?? ''),
                        trailing: AppPill(
                          text: r['approval_status'].toString().toUpperCase(),
                          color: r['approval_status'] == 'approved'
                              ? Colors.teal
                              : r['approval_status'] == 'rejected'
                              ? Colors.red
                              : Colors.orange,
                        ),
                      );
                    }).toList(),
                  ),
          ),
        ],
      ),
    );
  }
}
