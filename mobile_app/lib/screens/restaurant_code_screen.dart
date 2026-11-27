import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../core/app_mode.dart';
import '../core/i18n.dart';
import '../core/session.dart';
import '../widgets/common.dart';
import 'welcome_screen.dart' show showServerDialog;

/// أول شاشة: كود المطعم (المنصة فيها مطاعم كتير)
class RestaurantCodeScreen extends StatefulWidget {
  const RestaurantCodeScreen({super.key});

  @override
  State<RestaurantCodeScreen> createState() => _RestaurantCodeScreenState();
}

class _RestaurantCodeScreenState extends State<RestaurantCodeScreen> {
  final code = TextEditingController();
  bool loading = false;

  Future<void> submit() async {
    setState(() => loading = true);
    await guard(context, () => context.read<Session>().setRestaurant(code.text));
    if (mounted) setState(() => loading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(actions: [
        const LangButton(),
        IconButton(tooltip: tr('إعدادات السيرفر'), icon: const Icon(Icons.dns_outlined), onPressed: () => showServerDialog(context)),
      ]),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: ListView(shrinkWrap: true, padding: const EdgeInsets.all(24), children: [
              Icon(isDriverApp ? Icons.delivery_dining : Icons.storefront, size: 84, color: kBrand),
              const SizedBox(height: 16),
              Text(tr('كود المطعم'), textAlign: TextAlign.center, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800)),
              const SizedBox(height: 6),
              Text(tr('اكتب كود المطعم اللي هتطلب منه أو بتشتغل فيه'), textAlign: TextAlign.center, style: const TextStyle(color: Colors.grey)),
              const SizedBox(height: 24),
              TextField(
                controller: code,
                autofocus: true,
                textDirection: TextDirection.ltr,
                textAlign: TextAlign.center,
                autocorrect: false,
                onSubmitted: (_) => submit(),
                decoration: const InputDecoration(hintText: 'my-restaurant', prefixIcon: Icon(Icons.tag)),
              ),
              const SizedBox(height: 16),
              FilledButton(
                style: FilledButton.styleFrom(padding: const EdgeInsets.all(16)),
                onPressed: loading ? null : submit,
                child: loading
                    ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                    : Text(tr('متابعة')),
              ),
            ]),
          ),
        ),
      ),
    );
  }
}

/// اسم المطعم الحالي + تغييره (لو مش نسخة خاصة بمطعم)
class CurrentRestaurant extends StatelessWidget {
  const CurrentRestaurant({super.key});

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    return Column(children: [
      Text(session.restaurantName ?? session.api.tenant ?? '', textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
      if (!session.api.tenantLocked)
        TextButton.icon(
          onPressed: session.clearRestaurant,
          icon: const Icon(Icons.swap_horiz, size: 18),
          label: Text(tr('تغيير المطعم (:code)').replaceAll(':code', session.api.tenant ?? '')),
        ),
    ]);
  }
}
