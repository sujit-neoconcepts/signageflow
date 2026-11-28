import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import 'attendance_punch_screen.dart';
import 'leave_self_service_screen.dart';
import 'payslips_screen.dart';

class HrmsHubScreen extends StatefulWidget {
  const HrmsHubScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<HrmsHubScreen> createState() => HrmsHubScreenState();
}

class HrmsHubScreenState extends State<HrmsHubScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  final _attKey = GlobalKey<AttendancePunchScreenState>();
  final _leaveKey = GlobalKey<LeaveSelfServiceScreenState>();
  final _payslipsKey = GlobalKey<PayslipsScreenState>();

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> refresh() async {
    await _attKey.currentState?.refresh();
    await _leaveKey.currentState?.refresh();
    await _payslipsKey.currentState?.refresh();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        TabBar(
          controller: _tabController,
          tabs: const [
            Tab(icon: Icon(Icons.fingerprint_rounded), text: 'Attendance'),
            Tab(icon: Icon(Icons.event_available_rounded), text: 'Leave & FWA'),
            Tab(icon: Icon(Icons.receipt_long_rounded), text: 'Payslips'),
          ],
        ),
        Expanded(
          child: TabBarView(
            controller: _tabController,
            children: [
              AttendancePunchScreen(key: _attKey, api: widget.api),
              LeaveSelfServiceScreen(key: _leaveKey, api: widget.api),
              PayslipsScreen(key: _payslipsKey, api: widget.api),
            ],
          ),
        ),
      ],
    );
  }
}
