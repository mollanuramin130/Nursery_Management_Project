import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:nursery_app/data/mock_data_mode.dart';
import 'package:nursery_app/providers/offline_controller.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:provider/provider.dart';

/// DEBUG-only network / mock controls — never shown in release.
class DebugNetworkOverlay extends StatefulWidget {
  const DebugNetworkOverlay({super.key, required this.child});

  final Widget child;

  @override
  State<DebugNetworkOverlay> createState() => _DebugNetworkOverlayState();
}

class _DebugNetworkOverlayState extends State<DebugNetworkOverlay> {
  bool _open = false;

  @override
  Widget build(BuildContext context) {
    if (!kDebugMode) return widget.child;
    return Stack(
      alignment: Alignment.topLeft,
      children: [
        widget.child,
        Positioned(
          // QA-41: bottom-left above shell nav — avoids category row + banners.
          left: 12,
          bottom: 88,
          child: SafeArea(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (_open) const _DebugPanel(),
                FloatingActionButton.small(
                  heroTag: 'qa37_net_sim',
                  backgroundColor: AppColors.primaryDeep,
                  onPressed: () => setState(() => _open = !_open),
                  child: Icon(
                    _open ? Icons.close : Icons.wifi_tethering_error_rounded,
                    color: Colors.white,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _DebugPanel extends StatelessWidget {
  const _DebugPanel();

  @override
  Widget build(BuildContext context) {
    final offline = context.watch<OfflineController>();
    return Material(
      elevation: 6,
      borderRadius: BorderRadius.circular(12),
      color: Colors.white,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 280),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'QA Network Sim',
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppColors.primaryDeep,
                    ),
              ),
              const SizedBox(height: 8),
              Text('Mock mode', style: Theme.of(context).textTheme.labelMedium),
              Wrap(
                spacing: 6,
                children: MockDataMode.values.map((m) {
                  return ChoiceChip(
                    label: Text(m.name, style: const TextStyle(fontSize: 11)),
                    selected: offline.mode == m,
                    onSelected: (_) => offline.setMode(m),
                  );
                }).toList(),
              ),
              const SizedBox(height: 8),
              Text(
                'Simulation',
                style: Theme.of(context).textTheme.labelMedium,
              ),
              Wrap(
                spacing: 6,
                children: NetworkSimulation.values.map((s) {
                  return ChoiceChip(
                    label: Text(s.name, style: const TextStyle(fontSize: 11)),
                    selected: offline.simulation == s,
                    onSelected: (_) => offline.setSimulation(s),
                  );
                }).toList(),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
