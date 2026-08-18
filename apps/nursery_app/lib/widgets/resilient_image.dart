import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:nursery_app/data/mock_asset_store.dart';
import 'package:nursery_app/providers/offline_controller.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:provider/provider.dart';

/// Remote image with local asset fallback — never shows a broken-image icon.
///
/// QA-41: when [OfflineController.syncGeneration] bumps (Retry / reconnect),
/// cache keys change so previously-failed network images are retried.
class ResilientNetworkImage extends StatelessWidget {
  const ResilientNetworkImage({
    super.key,
    required this.url,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.borderRadius,
    this.memCacheWidth,
  });

  final String? url;
  final double? width;
  final double? height;
  final BoxFit fit;
  final BorderRadius? borderRadius;
  final int? memCacheWidth;

  @override
  Widget build(BuildContext context) {
    final child = _buildInner(context);
    if (borderRadius != null) {
      return ClipRRect(borderRadius: borderRadius!, child: child);
    }
    return child;
  }

  Widget _buildInner(BuildContext context) {
    final u = url?.trim();
    if (u == null || u.isEmpty) {
      return _Fallback(width: width, height: height);
    }
    var gen = 0;
    try {
      gen = context.watch<OfflineController>().syncGeneration;
    } catch (_) {
      // Not under OfflineController (tests / admin).
    }
    return CachedNetworkImage(
      imageUrl: u,
      cacheKey: '$u#g$gen',
      width: width,
      height: height,
      fit: fit,
      memCacheWidth: memCacheWidth,
      // Wikimedia blocks the default Dart User-Agent (403/429) and FilePath
      // redirects. Browser-like headers let upload.wikimedia.org images load.
      httpHeaders: const {
        'User-Agent':
            'Mozilla/5.0 (Linux; Android 12; Mobile) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Mobile Safari/537.36',
        'Accept': 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
      },
      placeholder: (_, __) => _Placeholder(width: width, height: height),
      errorWidget: (_, __, ___) => _Fallback(width: width, height: height),
    );
  }
}

/// Category / chip circle when API has no image_url (e.g. Admin Test Category).
class CategoryGlyphAvatar extends StatelessWidget {
  const CategoryGlyphAvatar({
    super.key,
    required this.name,
    this.size = 60,
  });

  final String name;
  final double size;

  @override
  Widget build(BuildContext context) {
    final trimmed = name.trim();
    final letter =
        trimmed.isEmpty ? 'G' : trimmed.substring(0, 1).toUpperCase();
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: const BoxDecoration(
        color: AppColors.primarySoft,
        shape: BoxShape.circle,
      ),
      child: Text(
        letter,
        style: TextStyle(
          color: AppColors.primaryDeep,
          fontWeight: FontWeight.w800,
          fontSize: size * 0.38,
        ),
      ),
    );
  }
}

/// Circle category image with glyph fallback (no solid green blank tile).
class CategoryCircleImage extends StatelessWidget {
  const CategoryCircleImage({
    super.key,
    required this.name,
    this.imageUrl,
    this.size = 60,
  });

  final String name;
  final String? imageUrl;
  final double size;

  @override
  Widget build(BuildContext context) {
    final u = imageUrl?.trim();
    return ClipOval(
      child: SizedBox(
        width: size,
        height: size,
        child: (u == null || u.isEmpty)
            ? CategoryGlyphAvatar(name: name, size: size)
            : ResilientNetworkImage(
                url: u,
                width: size,
                height: size,
                fit: BoxFit.cover,
                memCacheWidth: (size * 3).round(),
              ),
      ),
    );
  }
}

class _Placeholder extends StatelessWidget {
  const _Placeholder({this.width, this.height});
  final double? width;
  final double? height;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      color: AppColors.surfaceMuted,
      alignment: Alignment.center,
      child: const SizedBox(
        width: 22,
        height: 22,
        child: CircularProgressIndicator(strokeWidth: 2),
      ),
    );
  }
}

class _Fallback extends StatelessWidget {
  const _Fallback({this.width, this.height});
  final double? width;
  final double? height;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      color: AppColors.surfaceMuted,
      alignment: Alignment.center,
      child: Image.asset(
        MockAssetStore.placeholderImage,
        width: (width != null && width! < 48) ? width : 40,
        height: (height != null && height! < 48) ? height : 40,
        fit: BoxFit.contain,
        errorBuilder: (_, __, ___) => Icon(
          Icons.local_florist_rounded,
          color: AppColors.primary,
          size: 28,
        ),
      ),
    );
  }
}
