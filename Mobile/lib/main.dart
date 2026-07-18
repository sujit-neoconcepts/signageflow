import 'dart:convert';
import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:intl/intl.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await dotenv.load(fileName: '.env');
  runApp(const SignageFlowApp());
}

const _storage = FlutterSecureStorage();
const _accent = Color(0xFF0EA5E9);
const _ink = Color(0xFF0F172A);

String get _apiBaseUrl =>
    (dotenv.env['API_BASE_URL'] ?? '').trim().replaceAll(RegExp(r'/+$'), '');

class SignageFlowApp extends StatelessWidget {
  const SignageFlowApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'SignageFlow',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: _accent,
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
            borderSide: const BorderSide(color: _accent, width: 1.5),
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
    final token = await _storage.read(key: 'token');
    if (!mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(
        builder: (_) {
          if (_apiBaseUrl.isEmpty) {
            return const MissingConfigScreen();
          }

          return token == null
              ? const LoginScreen()
              : TaskHome(api: ApiClient(_apiBaseUrl, token));
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(body: Center(child: CircularProgressIndicator()));
  }
}

class ApiClient {
  ApiClient(this.baseUrl, this.token);

  final String baseUrl;
  final String token;

  Uri uri(String path, [Map<String, String?> query = const {}]) {
    final cleaned = baseUrl.endsWith('/')
        ? baseUrl.substring(0, baseUrl.length - 1)
        : baseUrl;
    final queryParameters = Map<String, String?>.from(query)
      ..removeWhere((_, value) => value == null || value.isEmpty);

    return Uri.parse(
      '$cleaned/api/mobile$path',
    ).replace(queryParameters: queryParameters);
  }

  Map<String, String> get headers => {
    'Accept': 'application/json',
    'Authorization': 'Bearer $token',
  };

  Future<Map<String, dynamic>> getJson(
    String path, [
    Map<String, String?> query = const {},
  ]) async {
    final response = await http.get(uri(path, query), headers: headers);
    return _decode(response);
  }

  Future<Map<String, dynamic>> postJson(
    String path,
    Map<String, dynamic> body,
  ) async {
    final response = await http.post(
      uri(path),
      headers: {...headers, 'Content-Type': 'application/json'},
      body: jsonEncode(body),
    );
    return _decode(response);
  }

  Future<Map<String, dynamic>> multipart(
    String path,
    Map<String, String> fields,
    List<File> files,
  ) async {
    final request = http.MultipartRequest('POST', uri(path))
      ..headers.addAll(headers)
      ..fields.addAll(fields);
    for (final file in files) {
      request.files.add(
        await http.MultipartFile.fromPath('files[]', file.path),
      );
    }
    final response = await http.Response.fromStream(await request.send());
    return _decode(response);
  }

  Future<void> downloadAndOpen(String url, String fileName) async {
    final response = await http.get(Uri.parse(url), headers: headers);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception('Download failed');
    }
    final dir = await getTemporaryDirectory();
    final file = File('${dir.path}/$fileName');
    await file.writeAsBytes(response.bodyBytes);
    await OpenFilex.open(file.path);
  }

  Future<void> logout() async {
    await http.post(uri('/logout'), headers: headers);
    await _storage.delete(key: 'token');
  }

  Map<String, dynamic> _decode(http.Response response) {
    final json = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode >= 200 && response.statusCode < 300) return json;
    final message =
        json['message'] ??
        (json['errors'] as Map?)?.values.firstOrNull?.first ??
        'Request failed';
    throw Exception(message);
  }
}

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _loading = false;
  String? _error;

  Future<void> _login() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await http.post(
        Uri.parse('$_apiBaseUrl/api/mobile/login'),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({
          'email': _email.text.trim(),
          'password': _password.text,
        }),
      );
      final data = jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw Exception(data['message'] ?? 'Login failed');
      }
      await _storage.write(key: 'token', value: data['token'] as String);
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) =>
              TaskHome(api: ApiClient(_apiBaseUrl, data['token'] as String)),
        ),
      );
    } catch (e) {
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFF0F172A), Color(0xFF0E7490), Color(0xFFF8FAFC)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(22),
              child: Container(
                constraints: const BoxConstraints(maxWidth: 460),
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: .96),
                  borderRadius: BorderRadius.circular(28),
                  boxShadow: const [
                    BoxShadow(
                      color: Colors.black26,
                      blurRadius: 34,
                      offset: Offset(0, 18),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: _ink,
                        borderRadius: BorderRadius.circular(18),
                      ),
                      child: const Icon(
                        Icons.task_alt_rounded,
                        color: Colors.white,
                        size: 34,
                      ),
                    ),
                    const SizedBox(height: 22),
                    const Text(
                      'SignageFlow',
                      style: TextStyle(
                        fontSize: 32,
                        fontWeight: FontWeight.w800,
                        color: _ink,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Mobile task and expense console',
                      style: TextStyle(
                        color: Colors.blueGrey.shade600,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 24),
                    TextField(
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      decoration: const InputDecoration(
                        labelText: 'Email',
                        prefixIcon: Icon(Icons.alternate_email_rounded),
                      ),
                    ),
                    const SizedBox(height: 14),
                    TextField(
                      controller: _password,
                      obscureText: true,
                      decoration: const InputDecoration(
                        labelText: 'Password',
                        prefixIcon: Icon(Icons.lock_rounded),
                      ),
                    ),
                    if (_error != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 14),
                        child: Text(
                          _error!,
                          style: const TextStyle(
                            color: Colors.red,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    const SizedBox(height: 22),
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: FilledButton.icon(
                        onPressed: _loading ? null : _login,
                        icon: _loading
                            ? const SizedBox.square(
                                dimension: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              )
                            : const Icon(Icons.login_rounded),
                        label: const Text('Sign in securely'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class MissingConfigScreen extends StatelessWidget {
  const MissingConfigScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: EdgeInsets.all(24),
            child: Text(
              'API_BASE_URL is missing. Configure Mobile/.env before running the app.',
              textAlign: TextAlign.center,
              style: TextStyle(fontWeight: FontWeight.w800, color: _ink),
            ),
          ),
        ),
      ),
    );
  }
}

class TaskHome extends StatefulWidget {
  const TaskHome({super.key, required this.api});

  final ApiClient api;

  @override
  State<TaskHome> createState() => _TaskHomeState();
}

class _TaskHomeState extends State<TaskHome> {
  final _search = TextEditingController();
  List<dynamic> _tasks = [];
  Map<String, dynamic> _meta = {};
  String _status = 'all';
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
      final results = await Future.wait([
        widget.api.getJson('/tasks', {
          'status': _status,
          'search': _search.text.trim(),
        }),
        if (_meta.isEmpty) widget.api.getJson('/tasks/meta'),
      ]);
      final page = results.first['tasks'] as Map<String, dynamic>;
      setState(() {
        _tasks = page['data'] as List<dynamic>;
        if (results.length > 1) _meta = results[1];
      });
    } catch (e) {
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
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
    final active = _tasks
        .where(
          (task) =>
              !['completed', 'verified', 'closed'].contains(task['my_status']),
        )
        .length;
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          'My Tasks',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        actions: [
          IconButton(
            onPressed: _logout,
            icon: const Icon(Icons.logout_rounded),
            tooltip: 'Logout',
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(18, 8, 18, 14),
                child: Column(
                  children: [
                    _HeroMetrics(total: _tasks.length, active: active),
                    const SizedBox(height: 14),
                    TextField(
                      controller: _search,
                      onSubmitted: (_) => _load(),
                      decoration: InputDecoration(
                        hintText: 'Search task title or description',
                        prefixIcon: const Icon(Icons.search_rounded),
                        suffixIcon: IconButton(
                          onPressed: _load,
                          icon: const Icon(Icons.tune_rounded),
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: Row(
                        children:
                            [
                              'all',
                              'pending',
                              'accepted',
                              'in_progress',
                              'completed',
                              'verified',
                              'closed',
                            ].map((status) {
                              return Padding(
                                padding: const EdgeInsets.only(right: 8),
                                child: ChoiceChip(
                                  label: Text(_label(status)),
                                  selected: _status == status,
                                  onSelected: (_) {
                                    setState(() => _status = status);
                                    _load();
                                  },
                                ),
                              );
                            }).toList(),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            if (_loading)
              const SliverFillRemaining(
                child: Center(child: CircularProgressIndicator()),
              ),
            if (_error != null)
              SliverFillRemaining(
                child: Center(
                  child: Text(
                    _error!,
                    style: const TextStyle(
                      color: Colors.red,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ),
            if (!_loading && _error == null && _tasks.isEmpty)
              const SliverFillRemaining(
                child: Center(child: Text('No tasks found.')),
              ),
            if (!_loading && _error == null)
              SliverList.builder(
                itemCount: _tasks.length,
                itemBuilder: (context, index) {
                  final task = _tasks[index] as Map<String, dynamic>;
                  return Padding(
                    padding: const EdgeInsets.fromLTRB(18, 0, 18, 12),
                    child: TaskCard(
                      task: task,
                      onTap: () async {
                        await Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => TaskDetailScreen(
                              api: widget.api,
                              task: task,
                              meta: _meta,
                            ),
                          ),
                        );
                        _load();
                      },
                    ),
                  );
                },
              ),
          ],
        ),
      ),
    );
  }
}

class _HeroMetrics extends StatelessWidget {
  const _HeroMetrics({required this.total, required this.active});

  final int total;
  final int active;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(26),
        gradient: const LinearGradient(
          colors: [_ink, Color(0xFF0E7490)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x330EA5E9),
            blurRadius: 24,
            offset: Offset(0, 14),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: _Metric(label: 'Assigned', value: '$total'),
          ),
          Container(width: 1, height: 52, color: Colors.white24),
          Expanded(
            child: _Metric(label: 'Active', value: '$active'),
          ),
          Container(width: 1, height: 52, color: Colors.white24),
          Expanded(
            child: _Metric(label: 'Done', value: '${total - active}'),
          ),
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          value,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 26,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(
          label,
          style: const TextStyle(
            color: Colors.white70,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}

class TaskCard extends StatelessWidget {
  const TaskCard({super.key, required this.task, required this.onTap});

  final Map<String, dynamic> task;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final priority = task['priority'] as String? ?? 'medium';
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
            Row(
              children: [
                Expanded(
                  child: Text(
                    task['title'] ?? '',
                    style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                      color: _ink,
                    ),
                  ),
                ),
                const Icon(Icons.chevron_right_rounded),
              ],
            ),
            if (task['job_name'] != null)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(
                  '${task['job_name']}  •  ${task['client_name'] ?? 'No client'}',
                  style: TextStyle(
                    color: Colors.blueGrey.shade600,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _Pill(
                  text: _label(task['my_status'] ?? 'viewer'),
                  color: _statusColor(task['my_status']),
                ),
                _Pill(
                  text: priority.toUpperCase(),
                  color: _priorityColor(priority),
                ),
                _Pill(
                  text: 'Due ${task['due_date'] ?? '-'}',
                  color: Colors.blueGrey,
                ),
                if (task['need_expense'] == true)
                  const _Pill(text: 'Expense', color: Colors.teal),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class TaskDetailScreen extends StatefulWidget {
  const TaskDetailScreen({
    super.key,
    required this.api,
    required this.task,
    required this.meta,
  });

  final ApiClient api;
  final Map<String, dynamic> task;
  final Map<String, dynamic> meta;

  @override
  State<TaskDetailScreen> createState() => _TaskDetailScreenState();
}

class _TaskDetailScreenState extends State<TaskDetailScreen> {
  Map<String, dynamic>? _detail;
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
      final data = await widget.api.getJson('/tasks/${widget.task['id']}');
      setState(() => _detail = data);
    } catch (e) {
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _quickStatus(String status) async {
    try {
      await widget.api.multipart('/tasks/${widget.task['id']}/status', {
        'status': status,
        'comment': 'Marked task as ${_label(status)}.',
      }, []);
      widget.task['my_status'] = status;
      _snack('Status updated');
      _load();
    } catch (e) {
      _snack(e.toString().replaceFirst('Exception: ', ''), error: true);
    }
  }

  void _snack(String message, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: error ? Colors.red : _ink,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final task = _detail?['task'] as Map<String, dynamic>?;
    return Scaffold(
      appBar: AppBar(
        title: Text(
          widget.task['title'] ?? 'Task',
          overflow: TextOverflow.ellipsis,
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? Center(
              child: Text(_error!, style: const TextStyle(color: Colors.red)),
            )
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(18),
                children: [
                  _DetailHeader(task: task!, summary: widget.task),
                  const SizedBox(height: 14),
                  _ActionPanel(
                    summary: widget.task,
                    task: task,
                    meta: widget.meta,
                    api: widget.api,
                    onQuickStatus: _quickStatus,
                    onChanged: _load,
                    snack: _snack,
                  ),
                  _FilesPanel(
                    api: widget.api,
                    title: 'Task Attachments',
                    files: (_detail!['files'] as List).cast<dynamic>(),
                  ),
                  _FilesPanel(
                    api: widget.api,
                    title: 'Job Attachments',
                    files: (_detail!['job_files'] as List).cast<dynamic>(),
                  ),
                  if (task['need_expense'] == true)
                    ExpensePanel(
                      api: widget.api,
                      taskId: widget.task['id'] as int,
                      taskStatus: widget.task['my_status'] as String? ?? '',
                      expenses: (_detail!['expenses'] as List).cast<dynamic>(),
                      meta: widget.meta,
                      taskTitle: widget.task['title'] ?? '',
                      jobName: widget.task['job_name'] ?? '',
                      onChanged: _load,
                      snack: _snack,
                    ),
                  CommentsPanel(
                    api: widget.api,
                    taskId: widget.task['id'] as int,
                    comments: (_detail!['comments'] as List).cast<dynamic>(),
                    canComment: ![
                      'verified',
                      'closed',
                    ].contains(widget.task['my_status']),
                    onChanged: _load,
                    snack: _snack,
                  ),
                ],
              ),
            ),
    );
  }
}

class _DetailHeader extends StatelessWidget {
  const _DetailHeader({required this.task, required this.summary});

  final Map<String, dynamic> task;
  final Map<String, dynamic> summary;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _Pill(
                text: _label(summary['my_status'] ?? task['status']),
                color: _statusColor(summary['my_status']),
              ),
              _Pill(
                text: (task['priority'] ?? '').toString().toUpperCase(),
                color: _priorityColor(task['priority']),
              ),
              _Pill(
                text: 'Due ${task['due_date'] ?? '-'}',
                color: Colors.blueGrey,
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            task['title'] ?? '',
            style: const TextStyle(
              fontSize: 24,
              height: 1.1,
              fontWeight: FontWeight.w900,
              color: _ink,
            ),
          ),
          const SizedBox(height: 10),
          if (summary['job_name'] != null)
            Text(
              '${summary['job_name']}  •  ${summary['client_name'] ?? 'No client'}',
              style: const TextStyle(
                fontWeight: FontWeight.w800,
                color: _accent,
              ),
            ),
          if (summary['start_date'] != null ||
              summary['estimated_hours'] != null)
            Padding(
              padding: const EdgeInsets.only(top: 10),
              child: Text(
                'Start: ${summary['start_date'] ?? 'Not scheduled'}  •  Est: ${summary['estimated_hours'] ?? 'N/A'} hrs',
                style: TextStyle(
                  color: Colors.blueGrey.shade700,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          const SizedBox(height: 14),
          Text(
            (task['description'] ?? 'No description provided.').toString(),
            style: TextStyle(color: Colors.blueGrey.shade700, height: 1.45),
          ),
        ],
      ),
    );
  }
}

class _ActionPanel extends StatelessWidget {
  const _ActionPanel({
    required this.summary,
    required this.task,
    required this.meta,
    required this.api,
    required this.onQuickStatus,
    required this.onChanged,
    required this.snack,
  });

  final Map<String, dynamic> summary;
  final Map<String, dynamic> task;
  final Map<String, dynamic> meta;
  final ApiClient api;
  final Future<void> Function(String status) onQuickStatus;
  final Future<void> Function() onChanged;
  final void Function(String message, {bool error}) snack;

  @override
  Widget build(BuildContext context) {
    final myStatus = summary['my_status'] as String? ?? '';
    if (summary['is_assignee'] != true ||
        ['verified', 'closed'].contains(myStatus)) {
      return const SizedBox.shrink();
    }
    final startRaw = summary['start_date_raw'] as String?;
    final canStart =
        startRaw == null || DateTime.now().isAfter(DateTime.parse(startRaw));

    return _Panel(
      title: 'Work Console',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (myStatus == 'pending')
            FilledButton.icon(
              onPressed: canStart ? () => onQuickStatus('in_progress') : null,
              icon: const Icon(Icons.play_arrow_rounded),
              label: Text(
                canStart
                    ? 'Accept and Start Task'
                    : 'Starts ${summary['start_date']}',
              ),
            ),
          if (myStatus == 'accepted')
            FilledButton.icon(
              onPressed: () => onQuickStatus('in_progress'),
              icon: const Icon(Icons.play_arrow_rounded),
              label: const Text('Start Work'),
            ),
          if (myStatus == 'in_progress')
            FilledButton.icon(
              onPressed: () => showModalBottomSheet(
                context: context,
                isScrollControlled: true,
                builder: (_) => CompleteTaskSheet(
                  api: api,
                  taskId: summary['id'] as int,
                  task: task,
                  meta: meta,
                  onChanged: onChanged,
                  snack: snack,
                ),
              ),
              icon: const Icon(Icons.verified_rounded),
              label: const Text('Submit Task for Verification'),
            ),
        ],
      ),
    );
  }
}

class CompleteTaskSheet extends StatefulWidget {
  const CompleteTaskSheet({
    super.key,
    required this.api,
    required this.taskId,
    required this.task,
    required this.meta,
    required this.onChanged,
    required this.snack,
  });

  final ApiClient api;
  final int taskId;
  final Map<String, dynamic> task;
  final Map<String, dynamic> meta;
  final Future<void> Function() onChanged;
  final void Function(String message, {bool error}) snack;

  @override
  State<CompleteTaskSheet> createState() => _CompleteTaskSheetState();
}

class _CompleteTaskSheetState extends State<CompleteTaskSheet> {
  final _comment = TextEditingController();
  final _enquiry = TextEditingController();
  final _sales = TextEditingController();
  final List<File> _files = [];
  bool _saving = false;

  Future<void> _pickFiles() async {
    final result = await FilePicker.platform.pickFiles(allowMultiple: true);
    if (result != null) {
      setState(
        () => _files.addAll(result.paths.whereType<String>().map(File.new)),
      );
    }
  }

  Future<void> _submit() async {
    setState(() => _saving = true);
    try {
      await widget.api.multipart('/tasks/${widget.taskId}/status', {
        'status': 'completed',
        'comment': _comment.text,
        if (_enquiry.text.trim().isNotEmpty) 'enquiry_no': _enquiry.text.trim(),
        if (_sales.text.trim().isNotEmpty) 'sales_order_no': _sales.text.trim(),
      }, _files);
      widget.snack('Task submitted for verification');
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
    final enquiryOptions =
        (widget.meta['enquiry_options'] as List?)?.cast<String>() ?? [];
    final salesOptions =
        (widget.meta['sales_order_options'] as List?)?.cast<String>() ?? [];
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
              'Complete Task',
              style: TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.w900,
                color: _ink,
              ),
            ),
            const SizedBox(height: 16),
            if (widget.task['need_enquiry_number'] == true)
              _AutocompleteField(
                controller: _enquiry,
                label: 'Enquiry Number',
                options: enquiryOptions,
              ),
            if (widget.task['need_sales_order_number'] == true)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: _AutocompleteField(
                  controller: _sales,
                  label: 'Sales Order Number',
                  options: salesOptions,
                ),
              ),
            const SizedBox(height: 12),
            TextField(
              controller: _comment,
              minLines: 3,
              maxLines: 5,
              decoration: const InputDecoration(labelText: 'Work summary'),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: _pickFiles,
              icon: const Icon(Icons.attach_file_rounded),
              label: Text(
                _files.isEmpty
                    ? 'Attach files'
                    : '${_files.length} file(s) attached',
              ),
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: _saving ? null : _submit,
              icon: const Icon(Icons.send_rounded),
              label: const Text('Submit'),
            ),
          ],
        ),
      ),
    );
  }
}

class ExpensePanel extends StatefulWidget {
  const ExpensePanel({
    super.key,
    required this.api,
    required this.taskId,
    required this.taskStatus,
    required this.expenses,
    required this.meta,
    required this.taskTitle,
    required this.jobName,
    required this.onChanged,
    required this.snack,
  });

  final ApiClient api;
  final int taskId;
  final String taskStatus;
  final List<dynamic> expenses;
  final Map<String, dynamic> meta;
  final String taskTitle;
  final String jobName;
  final Future<void> Function() onChanged;
  final void Function(String message, {bool error}) snack;

  @override
  State<ExpensePanel> createState() => _ExpensePanelState();
}

class _ExpensePanelState extends State<ExpensePanel> {
  final _amount = TextEditingController();
  final _details = TextEditingController();
  final _jobDetails = TextEditingController();
  String _type = 'Expense';
  String? _category;
  DateTime _date = DateTime.now();
  final Set<String> _doneBy = {};
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _jobDetails.text = widget.jobName;
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await widget.api.postJson('/tasks/${widget.taskId}/expenses', {
        'exp_date': DateFormat('yyyy-MM-dd').format(_date),
        'amount': _amount.text,
        'amt_type': _type,
        'exp_cate': _category,
        'details': _details.text,
        'job_details': _jobDetails.text,
        'doneby': _doneBy.toList(),
        'job_no': widget.taskTitle,
      });
      _amount.clear();
      _details.clear();
      widget.snack('Expense logged');
      await widget.onChanged();
    } catch (e) {
      widget.snack(e.toString().replaceFirst('Exception: ', ''), error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final categories =
        (widget.meta['expense_categories'] as List?)?.cast<String>() ?? [];
    final doneByOptions = ((widget.meta['expense_done_by'] as List?) ?? [])
        .map(
          (item) => item is Map
              ? (item['id'] ?? item['label']).toString()
              : item.toString(),
        )
        .where((item) => item.isNotEmpty)
        .toList();
    final total = widget.expenses.fold<double>(
      0,
      (sum, item) =>
          sum + double.parse((item['amount'] as String).replaceAll(',', '')),
    );
    return _Panel(
      title: 'Task Expenses',
      trailing: Text(
        'Total ${NumberFormat.currency(locale: 'en_IN', symbol: '₹').format(total)}',
        style: const TextStyle(fontWeight: FontWeight.w900, color: _accent),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          ...widget.expenses.map(
            (item) => ListTile(
              dense: true,
              contentPadding: EdgeInsets.zero,
              title: Text(
                '${item['exp_cate']}  •  ${item['amt_type']}',
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              subtitle: Text('${item['exp_date']}  ${item['details'] ?? ''}'),
              trailing: Text(
                item['amount'],
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
            ),
          ),
          if (![
            'completed',
            'verified',
            'closed',
          ].contains(widget.taskStatus)) ...[
            const Divider(height: 28),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.calendar_today_rounded),
              title: const Text(
                'Date',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
              subtitle: Text(DateFormat('dd-MM-yyyy').format(_date)),
              trailing: const Icon(Icons.edit_calendar_rounded),
              onTap: () async {
                final picked = await showDatePicker(
                  context: context,
                  initialDate: _date,
                  firstDate: DateTime.now().subtract(const Duration(days: 365)),
                  lastDate: DateTime.now().add(const Duration(days: 30)),
                );
                if (picked != null) {
                  setState(() => _date = picked);
                }
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
            TextField(
              controller: _jobDetails,
              decoration: const InputDecoration(
                labelText: 'Location / Job Details',
              ),
            ),
            const SizedBox(height: 10),
            if (doneByOptions.isNotEmpty) ...[
              const Text(
                'Done By',
                style: TextStyle(fontWeight: FontWeight.w800, color: _ink),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: doneByOptions.map((name) {
                  return FilterChip(
                    label: Text(name),
                    selected: _doneBy.contains(name),
                    onSelected: (selected) => setState(() {
                      selected ? _doneBy.add(name) : _doneBy.remove(name);
                    }),
                  );
                }).toList(),
              ),
              const SizedBox(height: 10),
            ],
            TextField(
              controller: _details,
              minLines: 2,
              maxLines: 4,
              decoration: const InputDecoration(labelText: 'Details'),
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: _saving ? null : _save,
              icon: const Icon(Icons.receipt_long_rounded),
              label: const Text('Log Expense'),
            ),
          ],
        ],
      ),
    );
  }
}

class CommentsPanel extends StatefulWidget {
  const CommentsPanel({
    super.key,
    required this.api,
    required this.taskId,
    required this.comments,
    required this.canComment,
    required this.onChanged,
    required this.snack,
  });

  final ApiClient api;
  final int taskId;
  final List<dynamic> comments;
  final bool canComment;
  final Future<void> Function() onChanged;
  final void Function(String message, {bool error}) snack;

  @override
  State<CommentsPanel> createState() => _CommentsPanelState();
}

class _CommentsPanelState extends State<CommentsPanel> {
  final _comment = TextEditingController();
  final _recorder = AudioRecorder();
  final List<File> _files = [];
  bool _recording = false;
  bool _saving = false;

  Future<void> _pickFiles() async {
    final result = await FilePicker.platform.pickFiles(allowMultiple: true);
    if (result != null) {
      setState(
        () => _files.addAll(result.paths.whereType<String>().map(File.new)),
      );
    }
  }

  Future<void> _toggleRecord() async {
    if (_recording) {
      final path = await _recorder.stop();
      if (path != null) setState(() => _files.add(File(path)));
      setState(() => _recording = false);
      return;
    }
    if (!await _recorder.hasPermission()) {
      widget.snack('Microphone permission is required.', error: true);
      return;
    }
    final dir = await getTemporaryDirectory();
    final path =
        '${dir.path}/voice_note_${DateTime.now().millisecondsSinceEpoch}.m4a';
    await _recorder.start(
      const RecordConfig(encoder: AudioEncoder.aacLc),
      path: path,
    );
    setState(() => _recording = true);
  }

  Future<void> _post() async {
    setState(() => _saving = true);
    try {
      await widget.api.multipart('/tasks/${widget.taskId}/comments', {
        'comment': _comment.text,
      }, _files);
      _comment.clear();
      _files.clear();
      widget.snack('Comment posted');
      await widget.onChanged();
    } catch (e) {
      widget.snack(e.toString().replaceFirst('Exception: ', ''), error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'History & Discussion',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (widget.comments.isEmpty)
            Text(
              'No discussions yet.',
              style: TextStyle(color: Colors.blueGrey.shade600),
            ),
          ...widget.comments.map(
            (item) => Container(
              margin: const EdgeInsets.only(bottom: 10),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${item['user']['name']}  •  ${item['created_at']}',
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      color: _ink,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(item['comment'] ?? ''),
                  ...((item['files'] as List?) ?? []).map(
                    (file) => TextButton.icon(
                      onPressed: () => widget.api.downloadAndOpen(
                        file['download_url'],
                        file['file_name'],
                      ),
                      icon: const Icon(Icons.attachment_rounded),
                      label: Text(file['file_name']),
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (widget.canComment) ...[
            const Divider(height: 24),
            TextField(
              controller: _comment,
              minLines: 2,
              maxLines: 4,
              onChanged: (_) => setState(() {}),
              decoration: const InputDecoration(labelText: 'Add comment'),
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              children: [
                OutlinedButton.icon(
                  onPressed: _pickFiles,
                  icon: const Icon(Icons.attach_file_rounded),
                  label: Text(
                    _files.isEmpty ? 'Attach' : '${_files.length} attached',
                  ),
                ),
                OutlinedButton.icon(
                  onPressed: _toggleRecord,
                  icon: Icon(
                    _recording ? Icons.stop_rounded : Icons.mic_rounded,
                  ),
                  label: Text(_recording ? 'Stop' : 'Voice'),
                ),
              ],
            ),
            const SizedBox(height: 10),
            FilledButton.icon(
              onPressed: _saving || _comment.text.trim().isEmpty ? null : _post,
              icon: const Icon(Icons.send_rounded),
              label: const Text('Post Comment'),
            ),
          ],
        ],
      ),
    );
  }
}

class _FilesPanel extends StatelessWidget {
  const _FilesPanel({
    required this.api,
    required this.title,
    required this.files,
  });

  final ApiClient api;
  final String title;
  final List<dynamic> files;

  @override
  Widget build(BuildContext context) {
    if (files.isEmpty) return const SizedBox.shrink();
    return _Panel(
      title: title,
      child: Column(
        children: files.map((file) {
          return ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.insert_drive_file_rounded),
            title: Text(file['file_name'], overflow: TextOverflow.ellipsis),
            subtitle: Text(_bytes(file['file_size'] ?? 0)),
            trailing: const Icon(Icons.open_in_new_rounded),
            onTap: () =>
                api.downloadAndOpen(file['download_url'], file['file_name']),
          );
        }).toList(),
      ),
    );
  }
}

class _Panel extends StatelessWidget {
  const _Panel({required this.title, required this.child, this.trailing});

  final String title;
  final Widget child;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                    color: _ink,
                  ),
                ),
              ),
              ?trailing,
            ],
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.text, required this.color});

  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .11),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: .22)),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _AutocompleteField extends StatelessWidget {
  const _AutocompleteField({
    required this.controller,
    required this.label,
    required this.options,
  });

  final TextEditingController controller;
  final String label;
  final List<String> options;

  @override
  Widget build(BuildContext context) {
    return Autocomplete<String>(
      optionsBuilder: (value) => options
          .where(
            (option) => option.toLowerCase().contains(value.text.toLowerCase()),
          )
          .take(20),
      onSelected: (value) => controller.text = value,
      fieldViewBuilder: (_, fieldController, focusNode, onSubmit) {
        fieldController.text = controller.text;
        fieldController.addListener(
          () => controller.text = fieldController.text,
        );
        return TextField(
          controller: fieldController,
          focusNode: focusNode,
          decoration: InputDecoration(labelText: label),
        );
      },
    );
  }
}

String _label(String value) => value
    .replaceAll('_', ' ')
    .split(' ')
    .map(
      (part) =>
          part.isEmpty ? part : '${part[0].toUpperCase()}${part.substring(1)}',
    )
    .join(' ');

Color _statusColor(dynamic value) {
  switch (value) {
    case 'pending':
      return Colors.amber.shade800;
    case 'accepted':
      return Colors.blue;
    case 'in_progress':
      return Colors.purple;
    case 'completed':
      return Colors.green;
    case 'verified':
      return Colors.teal;
    case 'closed':
      return Colors.blueGrey;
    default:
      return Colors.blueGrey;
  }
}

Color _priorityColor(dynamic value) {
  switch (value) {
    case 'urgent':
      return Colors.red;
    case 'high':
      return Colors.deepOrange;
    case 'medium':
      return _accent;
    default:
      return Colors.blueGrey;
  }
}

String _bytes(int bytes) {
  if (bytes <= 0) return '0 B';
  const sizes = ['B', 'KB', 'MB', 'GB'];
  var value = bytes.toDouble();
  var index = 0;
  while (value >= 1024 && index < sizes.length - 1) {
    value /= 1024;
    index++;
  }
  return '${value.toStringAsFixed(1)} ${sizes[index]}';
}
