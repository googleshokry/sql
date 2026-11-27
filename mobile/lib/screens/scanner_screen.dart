import 'package:material_ui/material_ui.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../l10n.dart';

/// Returns the first scanned barcode value.
class ScannerScreen extends StatefulWidget {
  const ScannerScreen({super.key});

  @override
  State<ScannerScreen> createState() => _ScannerScreenState();
}

class _ScannerScreenState extends State<ScannerScreen> {
  final _controller = MobileScannerController(detectionSpeed: DetectionSpeed.noDuplicates);
  bool _done = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(context.t('scan_barcode')),
        actions: [IconButton(onPressed: () => _controller.toggleTorch(), icon: const Icon(Icons.flash_on))],
      ),
      body: MobileScanner(
        controller: _controller,
        onDetect: (capture) {
          if (_done) return;
          final value = capture.barcodes.map((b) => b.rawValue).whereType<String>().firstOrNull;
          if (value != null && value.isNotEmpty) {
            _done = true;
            Navigator.pop(context, value);
          }
        },
      ),
    );
  }
}
