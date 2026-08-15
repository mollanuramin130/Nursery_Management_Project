import 'package:flutter/material.dart';
import 'package:nursery_app/core/network_errors.dart';
import 'package:nursery_app/providers/network_status_provider.dart';
import 'package:nursery_app/providers/offline_controller.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:provider/provider.dart';

/// Compact status for genuine offline/degraded/recovery — not normal refresh (QA-42A).
class NetworkStatusBanner extends StatelessWidget {
  const NetworkStatusBanner({super.key});

  @override
  Widget build(BuildContext context) {
    final net = context.watch<NetworkStatusProvider>();
    final offlineCtrl = context.watch<OfflineController>();

    // QA-43: one compact degraded banner. Never "online" / "back online" /
    // quiet cache peeks / reconnecting probes.
    final show = net.showBanner || offlineCtrl.servingLocal;
    if (!show) return const SizedBox.shrink();

    const text = "You're offline · Showing saved data";
    final bg = AppColors.warningSoft;
    final fg = AppColors.warning;

    return Semantics(
      liveRegion: true,
      label: text,
      child: AnimatedSize(
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOutCubic,
        alignment: Alignment.topCenter,
        child: Material(
          color: bg,
          child: SafeArea(
            bottom: false,
            child: Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: AppSpace.md,
                vertical: AppSpace.sm,
              ),
              child: Row(
                children: [
                  Icon(
                    net.kind == NetworkKind.offline
                        ? Icons.wifi_off_rounded
                        : Icons.cloud_off_outlined,
                    size: 18,
                    color: fg,
                  ),
                  const SizedBox(width: AppSpace.sm),
                  const Expanded(
                    child: Text(
                      text,
                      style: TextStyle(
                        color: AppColors.warning,
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                      ),
                    ),
                  ),
                  TextButton(
                    onPressed: () {
                      offlineCtrl.bumpSyncGeneration();
                      net.retryNow();
                    },
                    style: TextButton.styleFrom(
                      foregroundColor: fg,
                      visualDensity: VisualDensity.compact,
                    ),
                    child: const Text('Retry'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
