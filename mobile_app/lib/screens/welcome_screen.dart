import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../core/api.dart';
import '../core/session.dart';
import '../widgets/common.dart';
import 'restaurant_code_screen.dart';
import '../core/i18n.dart';

class WelcomeScreen extends StatelessWidget {
  const WelcomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(actions: [
        const LangButton(),
        IconButton(
          tooltip: tr('إعدادات السيرفر'),
          icon: const Icon(Icons.dns_outlined),
          onPressed: () => showServerDialog(context),
        ),
      ]),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                const Icon(Icons.restaurant_menu, size: 90, color: kBrand),
                const SizedBox(height: 12),
                Text(tr('أهلاً بيك'), textAlign: TextAlign.center, style: const TextStyle(fontSize: 18, color: Colors.grey)),
                const CurrentRestaurant(),
                const SizedBox(height: 40),
                FilledButton.icon(
                  style: FilledButton.styleFrom(padding: const EdgeInsets.all(18)),
                  icon: const Icon(Icons.delivery_dining),
                  label: Text(tr('اطلب أكلك (عميل)'), style: TextStyle(fontSize: 17)),
                  onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CustomerAuthScreen())),
                ),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(padding: const EdgeInsets.all(18)),
                  icon: const Icon(Icons.badge_outlined),
                  label: Text(tr('دخول الموظفين (كاشير / مدير / طيار)'), style: TextStyle(fontSize: 15)),
                  onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StaffLoginScreen())),
                ),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}

Future<void> showServerDialog(BuildContext context) async {
  final ctrl = TextEditingController(text: Api.instance.serverUrl);
  final url = await showDialog<String>(
    context: context,
    builder: (c) => AlertDialog(
      title: Text(tr('رابط السيرفر')),
      content: TextField(
        controller: ctrl,
        textDirection: TextDirection.ltr,
        keyboardType: TextInputType.url,
        decoration: InputDecoration(hintText: 'https://pos.example.com', helperText: tr('رابط نظام الكاشير على الإنترنت أو الشبكة المحلية')),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(c), child: Text(tr('إلغاء'))),
        FilledButton(onPressed: () => Navigator.pop(c, ctrl.text), child: Text(tr('حفظ'))),
      ],
    ),
  );
  if (url != null && url.trim().isNotEmpty) {
    await Api.instance.setServerUrl(url);
    if (context.mounted) toast(context, tr('تم الحفظ'));
  }
}

class StaffLoginScreen extends StatefulWidget {
  const StaffLoginScreen({super.key, this.driverApp = false});
  final bool driverApp;

  @override
  State<StaffLoginScreen> createState() => _StaffLoginScreenState();
}

class _StaffLoginScreenState extends State<StaffLoginScreen> {
  final email = TextEditingController();
  final password = TextEditingController();
  bool loading = false;

  Future<void> submit() async {
    setState(() => loading = true);
    final ok = await guard(context, () async {
      await context.read<Session>().staffLogin(email.text.trim(), password.text);
      return true;
    });
    if (!mounted) return;
    setState(() => loading = false);
    if (ok == true && Navigator.canPop(context)) Navigator.popUntil(context, (r) => r.isFirst);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.driverApp ? tr('طيار المطعم') : tr('دخول الموظفين')),
        actions: [
          if (widget.driverApp) ...[
            const LangButton(),
            IconButton(tooltip: tr('إعدادات السيرفر'), icon: const Icon(Icons.dns_outlined), onPressed: () => showServerDialog(context)),
          ],
        ],
      ),
      body: ListView(padding: const EdgeInsets.all(24), children: [
        if (widget.driverApp) ...[
          const Icon(Icons.delivery_dining, size: 90, color: kBrand),
          const SizedBox(height: 8),
          const CurrentRestaurant(),
          const SizedBox(height: 16),
        ],
        TextField(
          controller: email,
          textDirection: TextDirection.ltr,
          keyboardType: TextInputType.emailAddress,
          decoration: InputDecoration(labelText: tr('البريد'), prefixIcon: Icon(Icons.person_outline)),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: password,
          obscureText: true,
          onSubmitted: (_) => submit(),
          decoration: InputDecoration(labelText: tr('كلمة المرور'), prefixIcon: Icon(Icons.lock_outline)),
        ),
        const SizedBox(height: 24),
        FilledButton(
          style: FilledButton.styleFrom(padding: const EdgeInsets.all(16)),
          onPressed: loading ? null : submit,
          child: loading ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2)) : Text(tr('دخول')),
        ),
      ]),
    );
  }
}

class CustomerAuthScreen extends StatefulWidget {
  const CustomerAuthScreen({super.key});

  @override
  State<CustomerAuthScreen> createState() => _CustomerAuthScreenState();
}

class _CustomerAuthScreenState extends State<CustomerAuthScreen> {
  bool register = false;
  bool loading = false;
  final name = TextEditingController();
  final phone = TextEditingController();
  final password = TextEditingController();

  Future<void> submit() async {
    setState(() => loading = true);
    final session = context.read<Session>();
    final ok = await guard(context, () async {
      if (register) {
        await session.customerRegister({'name': name.text.trim(), 'phone': phone.text.trim(), 'password': password.text});
      } else {
        await session.customerLogin(phone.text.trim(), password.text);
      }
      return true;
    });
    if (!mounted) return;
    setState(() => loading = false);
    if (ok == true) Navigator.popUntil(context, (r) => r.isFirst);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(register ? tr('حساب جديد') : tr('تسجيل الدخول'))),
      body: ListView(padding: const EdgeInsets.all(24), children: [
        if (register) ...[
          TextField(controller: name, decoration: InputDecoration(labelText: tr('الاسم'), prefixIcon: Icon(Icons.person_outline))),
          const SizedBox(height: 12),
        ],
        TextField(
          controller: phone,
          keyboardType: TextInputType.phone,
          textDirection: TextDirection.ltr,
          decoration: InputDecoration(labelText: tr('رقم الموبايل'), prefixIcon: Icon(Icons.phone_outlined)),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: password,
          obscureText: true,
          onSubmitted: (_) => submit(),
          decoration: InputDecoration(labelText: tr('كلمة المرور'), prefixIcon: Icon(Icons.lock_outline)),
        ),
        const SizedBox(height: 24),
        FilledButton(
          style: FilledButton.styleFrom(padding: const EdgeInsets.all(16)),
          onPressed: loading ? null : submit,
          child: loading
              ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
              : Text(register ? tr('إنشاء الحساب') : tr('دخول')),
        ),
        TextButton(
          onPressed: () => setState(() => register = !register),
          child: Text(register ? tr('عندي حساب — تسجيل الدخول') : tr('ليس لديك حساب؟ سجل الآن')),
        ),
      ]),
    );
  }
}
