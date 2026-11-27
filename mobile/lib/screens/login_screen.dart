import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../l10n.dart';
import '../state/app_state.dart';
import '../widgets/common.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _server;
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;
  bool _obscure = true;

  @override
  void initState() {
    super.initState();
    final state = context.read<AppState>();
    _server = TextEditingController(text: state.serverUrl);
    _email.text = state.userEmail ?? '';
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      await context.read<AppState>().login(_server.text, _email.text, _password.text);
    } catch (e) {
      if (mounted) showMessage(context, e.toString(), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final scheme = Theme.of(context).colorScheme;
    String? required(String? v) => (v == null || v.trim().isEmpty) ? context.t('required') : null;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _form,
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Align(
                    alignment: AlignmentDirectional.centerEnd,
                    child: SegmentedButton<String>(
                      segments: const [
                        ButtonSegment(value: 'ar', label: Text('العربية')),
                        ButtonSegment(value: 'en', label: Text('English')),
                      ],
                      selected: {state.locale.languageCode},
                      onSelectionChanged: (v) => state.setLocale(v.first),
                    ),
                  ),
                  const SizedBox(height: 32),
                  CircleAvatar(
                    radius: 40,
                    backgroundColor: scheme.primaryContainer,
                    child: Icon(Icons.storefront, size: 40, color: scheme.primary),
                  ),
                  const SizedBox(height: 12),
                  Text(context.t('app_name'), textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 32),
                  TextFormField(
                    controller: _server,
                    keyboardType: TextInputType.url,
                    textDirection: TextDirection.ltr,
                    decoration: InputDecoration(
                      labelText: context.t('server_url'),
                      hintText: context.t('server_hint'),
                      prefixIcon: const Icon(Icons.dns_outlined),
                      border: const OutlineInputBorder(),
                    ),
                    validator: required,
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _email,
                    keyboardType: TextInputType.emailAddress,
                    textDirection: TextDirection.ltr,
                    decoration: InputDecoration(labelText: context.t('email'), prefixIcon: const Icon(Icons.email_outlined), border: const OutlineInputBorder()),
                    validator: required,
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _password,
                    obscureText: _obscure,
                    decoration: InputDecoration(
                      labelText: context.t('password'),
                      prefixIcon: const Icon(Icons.lock_outline),
                      border: const OutlineInputBorder(),
                      suffixIcon: IconButton(icon: Icon(_obscure ? Icons.visibility : Icons.visibility_off), onPressed: () => setState(() => _obscure = !_obscure)),
                    ),
                    validator: required,
                    onFieldSubmitted: (_) => _submit(),
                  ),
                  const SizedBox(height: 24),
                  FilledButton(
                    onPressed: _busy ? null : _submit,
                    style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 16)),
                    child: _busy
                        ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                        : Text(context.t('login')),
                  ),
                ]),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
