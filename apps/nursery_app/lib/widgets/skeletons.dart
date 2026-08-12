import 'package:flutter/material.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/ui_kit.dart';

/// Page-level skeletons that mirror real layouts.
abstract final class AppSkeletons {
  static Widget home() => const HomeSkeleton();
  static Widget productGrid({int count = 4}) =>
      ProductGridSkeleton(count: count);
  static Widget productDetail() => const ProductDetailSkeleton();
  static Widget cart() => const CartSkeleton();
  static Widget orders() => const OrdersSkeleton();
  static Widget account() => const AccountSkeleton();
  static Widget categories() => const CategoryGridSkeleton();
}

class HomeSkeleton extends StatelessWidget {
  const HomeSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(AppSpace.screen),
        children: const [
          SkeletonBox(height: AppTouch.iconButton, radius: AppRadii.full),
          SizedBox(height: AppSpace.lg),
          SkeletonBox(height: 180, radius: AppRadii.xl),
          SizedBox(height: AppSpace.xxl),
          SkeletonBox(height: 20, width: 140),
          SizedBox(height: AppSpace.md),
          SkeletonBox(height: 88, radius: AppRadii.lg),
          SizedBox(height: AppSpace.xxl),
          SkeletonBox(height: 20, width: 160),
          SizedBox(height: AppSpace.md),
          Row(
            children: [
              Expanded(child: ProductCardSkeleton()),
              SizedBox(width: AppSpace.md),
              Expanded(child: ProductCardSkeleton()),
            ],
          ),
        ],
      ),
    );
  }
}

class ProductGridSkeleton extends StatelessWidget {
  const ProductGridSkeleton({super.key, this.count = 4});

  final int count;

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      padding: const EdgeInsets.all(AppSpace.screen),
      itemCount: count,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: AppSpace.md,
        crossAxisSpacing: AppSpace.md,
        childAspectRatio: AppLayout.productGridAspectRatio,
      ),
      itemBuilder: (context, index) => const ProductCardSkeleton(),
    );
  }
}

class ProductDetailSkeleton extends StatelessWidget {
  const ProductDetailSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(AppSpace.screen),
      children: const [
        AspectRatio(
          aspectRatio: 4 / 5,
          child: SkeletonBox(height: double.infinity, radius: AppRadii.xl),
        ),
        SizedBox(height: AppSpace.lg),
        SkeletonBox(height: 28, width: 220),
        SizedBox(height: AppSpace.sm),
        SkeletonBox(height: 16, width: 120),
        SizedBox(height: AppSpace.md),
        SkeletonBox(height: 24, width: 100),
        SizedBox(height: AppSpace.xl),
        SkeletonBox(height: 80, radius: AppRadii.lg),
      ],
    );
  }
}

class CartSkeleton extends StatelessWidget {
  const CartSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(AppSpace.screen),
      children: List.generate(
        3,
        (_) => const Padding(
          padding: EdgeInsets.only(bottom: AppSpace.md),
          child: Row(
            children: [
              SkeletonBox(height: 72, width: 72, radius: AppRadii.md),
              SizedBox(width: AppSpace.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SkeletonBox(height: 16, width: 160),
                    SizedBox(height: AppSpace.sm),
                    SkeletonBox(height: 14, width: 80),
                    SizedBox(height: AppSpace.sm),
                    SkeletonBox(height: 32, width: 100, radius: AppRadii.full),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class OrdersSkeleton extends StatelessWidget {
  const OrdersSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(AppSpace.screen),
      children: List.generate(
        4,
        (_) => const Padding(
          padding: EdgeInsets.only(bottom: AppSpace.md),
          child: SkeletonBox(height: 88, radius: AppRadii.lg),
        ),
      ),
    );
  }
}

class AccountSkeleton extends StatelessWidget {
  const AccountSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(AppSpace.screen),
      children: const [
        SkeletonBox(height: 72, radius: AppRadii.lg),
        SizedBox(height: AppSpace.lg),
        SkeletonBox(height: 48, radius: AppRadii.md),
        SizedBox(height: AppSpace.sm),
        SkeletonBox(height: 48, radius: AppRadii.md),
        SizedBox(height: AppSpace.sm),
        SkeletonBox(height: 48, radius: AppRadii.md),
      ],
    );
  }
}

class AddressesSkeleton extends StatelessWidget {
  const AddressesSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(AppSpace.screen),
      children: const [
        SkeletonBox(height: 140, radius: AppRadii.lg),
        SizedBox(height: AppSpace.md),
        SkeletonBox(height: 140, radius: AppRadii.lg),
        SizedBox(height: AppSpace.md),
        SkeletonBox(height: 48, radius: AppRadii.full),
      ],
    );
  }
}

class CategoryGridSkeleton extends StatelessWidget {
  const CategoryGridSkeleton({super.key, this.count = 6});

  final int count;

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      padding: const EdgeInsets.all(AppSpace.screen),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: AppSpace.md,
        crossAxisSpacing: AppSpace.md,
        childAspectRatio: 1.1,
      ),
      itemCount: count,
      itemBuilder: (context, index) =>
          const SkeletonBox(height: double.infinity, radius: AppRadii.lg),
    );
  }
}
