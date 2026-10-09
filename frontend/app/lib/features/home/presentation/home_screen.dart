import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/presentation/app_brand_logo.dart';
import '../../../core/presentation/app_empty_view.dart';
import '../../../core/presentation/app_error_view.dart';
import '../../../core/presentation/app_loading_view.dart';
import '../../../core/presentation/async_view_state.dart';
import '../../../navigation/app_routes.dart';
import '../../../theme/app_color_extensions.dart';
import '../../../theme/app_spacing.dart';
import '../../catalog/data/catalog_repository.dart';
import '../../catalog/data/product_summary.dart';
import '../../catalog/presentation/catalog_controller.dart';
import '../../catalog/presentation/catalog_image.dart';
import '../../catalog/presentation/product_card.dart';
import '../../categories/data/category_detail.dart';
import '../../categories/data/fixture_categories.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    required this.repository,
    this.showFixtureHero = false,
  });
  final CatalogRepository repository;
  final bool showFixtureHero;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late final CatalogController _controller = CatalogController(
    widget.repository,
  )..load();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const AppBrandLogo()),
    body: ListenableBuilder(
      listenable: _controller,
      builder: (context, _) => CustomScrollView(
        slivers: <Widget>[
          SliverToBoxAdapter(
            child: _Hero(
              onExplore: () => context.push(AppRoutes.products),
              showFixtureImage: widget.showFixtureHero,
            ),
          ),
          if (widget.showFixtureHero) const _FixtureCategories(),
          switch (_controller.state) {
            AsyncInitial() ||
            AsyncLoading(previousData: null) => const SliverFillRemaining(
              child: AppLoadingView(message: 'Loading furniture'),
            ),
            AsyncEmpty() => SliverFillRemaining(
              child: AppEmptyView(
                title: 'Furniture is being added',
                description:
                    'Explore made-to-order furniture when listings are available.',
                actionLabel: 'Refresh',
                onAction: _controller.refresh,
              ),
            ),
            AsyncFailure(previousData: null, :final error) =>
              SliverFillRemaining(
                child: AppErrorView(error: error, onRecovery: _controller.load),
              ),
            AsyncContent(:final data) ||
            AsyncRefreshing(:final data) ||
            AsyncLoading(previousData: final data?) ||
            AsyncFailure(
              previousData: final data?,
            ) => _FeaturedProducts(products: data.products),
            _ => const SliverToBoxAdapter(),
          },
          const SliverToBoxAdapter(child: _CategoryDirectory()),
          SliverToBoxAdapter(
            child: _RequestIntroduction(
              onExplore: () => context.push(AppRoutes.products),
            ),
          ),
        ],
      ),
    ),
  );
}

/// Entry point to the full category index.
///
/// The home room strip is a preview; it links each tile straight to a landing
/// page but cannot show every category at once, so the catalog needs its own
/// way into the complete list rather than leaving `/categories` unreachable
/// from the storefront.
class _CategoryDirectory extends StatelessWidget {
  const _CategoryDirectory();

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(
      AppSpacing.gutterPhone,
      AppSpacing.space7,
      AppSpacing.gutterPhone,
      AppSpacing.space6,
    ),
    child: OutlinedButton(
      onPressed: () => context.push(AppRoutes.categories),
      child: const Text('Browse all categories'),
    ),
  );
}

class _FixtureCategories extends StatelessWidget {
  const _FixtureCategories();

  @override
  Widget build(BuildContext context) => SliverPadding(
    padding: const EdgeInsets.fromLTRB(
      AppSpacing.gutterPhone,
      AppSpacing.space7,
      AppSpacing.gutterPhone,
      AppSpacing.space3,
    ),
    sliver: SliverMainAxisGroup(
      slivers: <Widget>[
        SliverToBoxAdapter(
          child: Text(
            'Start with your space',
            style: Theme.of(context).textTheme.headlineLarge,
          ),
        ),
        const SliverToBoxAdapter(child: SizedBox(height: AppSpacing.space2)),
        SliverToBoxAdapter(
          child: Text(
            'Furniture for the everyday moments, room by room.',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
        ),
        const SliverToBoxAdapter(child: SizedBox(height: AppSpacing.space5)),
        SliverToBoxAdapter(
          child: SizedBox(
            height: AppSpacing.space10 * 5,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: fixtureCategoryDetails.length,
              separatorBuilder: (_, _) =>
                  const SizedBox(width: AppSpacing.space3),
              itemBuilder: (_, index) =>
                  _CategoryCard(category: fixtureCategoryDetails[index]),
            ),
          ),
        ),
      ],
    ),
  );
}

class _CategoryCard extends StatelessWidget {
  const _CategoryCard({required this.category});

  /// Development-fixture room story, shared with the categories feature so the
  /// home strip and the category index cannot drift into two taxonomies.
  final CategoryDetail category;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: AppSpacing.space10 * 4,
    child: Material(
      color: Theme.of(context).colorScheme.surface,
      child: InkWell(
        onTap: () => context.push(AppRoutes.category(category.slug)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Expanded(child: _image(context)),
            Padding(
              padding: const EdgeInsets.all(AppSpacing.space2),
              child: Text(
                category.name,
                style: Theme.of(context).textTheme.titleSmall,
              ),
            ),
          ],
        ),
      ),
    ),
  );

  Widget _image(BuildContext context) {
    final image = category.image;
    if (image == null) {
      return ColoredBox(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
      );
    }
    return CatalogImage(url: image.url, assetPath: image.assetPath);
  }
}

class _Hero extends StatelessWidget {
  const _Hero({required this.onExplore, required this.showFixtureImage});
  final VoidCallback onExplore;
  final bool showFixtureImage;
  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).extension<AppSurfaceColors>()!;
    return ColoredBox(
      color: colors.editorial,
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.space7),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            if (showFixtureImage) ...<Widget>[
              Image.asset(
                'assets/furnitures/fixtures/hero/hero.jpg',
                width: double.infinity,
                fit: BoxFit.cover,
                semanticLabel: 'A curated living space with chairs',
              ),
              const SizedBox(height: AppSpacing.space6),
            ],
            Text(
              'Furniture for the way you live.',
              style: Theme.of(context).textTheme.displaySmall,
            ),
            const SizedBox(height: AppSpacing.space4),
            Text(
              'Thoughtful pieces, made for your space.',
              style: Theme.of(context).textTheme.bodyLarge,
            ),
            const SizedBox(height: AppSpacing.space6),
            FilledButton(
              onPressed: onExplore,
              child: const Text('Explore furniture'),
            ),
          ],
        ),
      ),
    );
  }
}

class _FeaturedProducts extends StatelessWidget {
  const _FeaturedProducts({required this.products});
  final List<ProductSummary> products;
  @override
  Widget build(BuildContext context) => SliverPadding(
    padding: const EdgeInsets.all(AppSpacing.gutterPhone),
    sliver: SliverMainAxisGroup(
      slivers: <Widget>[
        SliverToBoxAdapter(
          child: Text(
            'Made for your space',
            style: Theme.of(context).textTheme.headlineLarge,
          ),
        ),
        const SliverToBoxAdapter(child: SizedBox(height: AppSpacing.space5)),
        SliverList.builder(
          itemCount: products.length,
          itemBuilder: (context, index) => Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.space6),
            child: ProductCard(
              product: products[index],
              onTap: () =>
                  context.push(AppRoutes.product(products[index].slug)),
            ),
          ),
        ),
        SliverToBoxAdapter(
          child: OutlinedButton(
            onPressed: () => context.push(AppRoutes.products),
            child: const Text('View all furniture'),
          ),
        ),
      ],
    ),
  );
}

class _RequestIntroduction extends StatelessWidget {
  const _RequestIntroduction({required this.onExplore});
  final VoidCallback onExplore;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(AppSpacing.space7),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text('Made to order', style: Theme.of(context).textTheme.headlineLarge),
        const SizedBox(height: AppSpacing.space3),
        Text(
          'Discover pieces for your space, then share what you need with our team.',
          style: Theme.of(context).textTheme.bodyLarge,
        ),
        const SizedBox(height: AppSpacing.space5),
        TextButton(onPressed: onExplore, child: const Text('Browse furniture')),
      ],
    ),
  );
}
