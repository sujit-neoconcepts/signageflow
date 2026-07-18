import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/app_config.dart';
import '../../core/network/api_client.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  List<dynamic> _notifications = [];
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
      final data = await widget.api.getJson('/notifications');
      setState(() {
        _notifications = data['data'] as List<dynamic>;
      });
    } catch (e) {
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _markAsRead(int id, int index) async {
    try {
      final response = await widget.api.postJson('/notifications/$id/read', {});
      setState(() {
        _notifications[index] = response['notification'];
      });
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(e.toString().replaceFirst('Exception: ', '')),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  Future<void> _markAllAsRead() async {
    if (_notifications.every((n) => n['read_at'] != null)) return;

    setState(() {
      _loading = true;
    });
    try {
      await widget.api.postJson('/notifications/read-all', {});
      setState(() {
        for (var i = 0; i < _notifications.length; i++) {
          final n = Map<String, dynamic>.from(_notifications[i]);
          if (n['read_at'] == null) {
            n['read_at'] = DateTime.now().toIso8601String();
            _notifications[i] = n;
          }
        }
      });
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(e.toString().replaceFirst('Exception: ', '')),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _formatDate(String dateStr) {
    try {
      final dt = DateTime.parse(dateStr).toLocal();
      return DateFormat('dd MMM yyyy, hh:mm a').format(dt);
    } catch (_) {
      return dateStr;
    }
  }

  @override
  Widget build(BuildContext context) {
    final unreadCount = _notifications.where((n) => n['read_at'] == null).length;

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Notifications',
              style: TextStyle(fontWeight: FontWeight.w900, fontSize: 20),
            ),
            if (unreadCount > 0)
              Text(
                '$unreadCount unread',
                style: const TextStyle(
                  fontSize: 12,
                  color: appAccent,
                  fontWeight: FontWeight.w700,
                ),
              ),
          ],
        ),
        actions: [
          if (_notifications.isNotEmpty && unreadCount > 0)
            TextButton.icon(
              onPressed: _markAllAsRead,
              icon: const Icon(Icons.done_all_rounded, size: 18),
              label: const Text('Mark all read'),
              style: TextButton.styleFrom(
                foregroundColor: appAccent,
                textStyle: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
          const SizedBox(width: 8),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _buildContent(),
      ),
    );
  }

  Widget _buildContent() {
    if (_loading && _notifications.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null && _notifications.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline_rounded, color: Colors.red, size: 48),
              const SizedBox(height: 14),
              Text(
                _error!,
                textAlign: TextAlign.center,
                style: const TextStyle(fontWeight: FontWeight.w700, color: appInk),
              ),
              const SizedBox(height: 14),
              FilledButton.icon(
                onPressed: _load,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }

    if (_notifications.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          SizedBox(
            height: MediaQuery.of(context).size.height * 0.6,
            child: Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        color: appAccent.withValues(alpha: 0.08),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.notifications_none_rounded,
                        color: appAccent,
                        size: 64,
                      ),
                    ),
                    const SizedBox(height: 20),
                    const Text(
                      'All caught up!',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                        color: appInk,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'No new notifications at the moment.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.blueGrey.shade600,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      );
    }

    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      itemCount: _notifications.length,
      itemBuilder: (context, index) {
        final notification = _notifications[index];
        final id = notification['id'] as int;
        final title = notification['title'] as String? ?? 'No Title';
        final body = notification['body'] as String? ?? '';
        final isUnread = notification['read_at'] == null;
        final dateStr = notification['created_at'] as String;

        return Card(
          margin: const EdgeInsets.only(bottom: 12),
          elevation: 0,
          color: isUnread ? Colors.white : const Color(0xFFF1F5F9),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
            side: BorderSide(
              color: isUnread ? appAccent.withValues(alpha: 0.2) : const Color(0xFFE2E8F0),
              width: isUnread ? 1.5 : 1.0,
            ),
          ),
          child: InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: isUnread ? () => _markAsRead(id, index) : null,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (isUnread)
                    Container(
                      margin: const EdgeInsets.only(top: 6, right: 12),
                      width: 8,
                      height: 8,
                      decoration: const BoxDecoration(
                        color: appAccent,
                        shape: BoxShape.circle,
                      ),
                    )
                  else
                    const SizedBox(width: 8),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title,
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: isUnread ? FontWeight.w900 : FontWeight.w700,
                            color: isUnread ? appInk : Colors.blueGrey.shade800,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          body,
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: isUnread ? FontWeight.w600 : FontWeight.w500,
                            color: isUnread ? Colors.blueGrey.shade800 : Colors.blueGrey.shade500,
                          ),
                        ),
                        const SizedBox(height: 10),
                        Text(
                          _formatDate(dateStr),
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: Colors.blueGrey.shade400,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (isUnread)
                    IconButton(
                      icon: const Icon(Icons.done_rounded, color: appAccent, size: 20),
                      onPressed: () => _markAsRead(id, index),
                      tooltip: 'Mark as read',
                    ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
