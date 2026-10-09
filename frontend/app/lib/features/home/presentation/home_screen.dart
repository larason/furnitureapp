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
import '../../catalog/presentation/product_card.dart';

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
              itemCount: _categories.length,
              separatorBuilder: (_, _) =>
                  const SizedBox(width: AppSpacing.space3),
              itemBuilder: (_, index) =>
                  _CategoryCard(category: _categories[index]),
            ),
          ),
        ),
      ],
    ),
  );
}

class _CategoryCard extends StatelessWidget {
  const _CategoryCard({required this.category});
  final _FixtureCategory category;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: AppSpacing.space10 * 4,
    child: Material(
      color: Theme.of(context).colorScheme.surface,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Expanded(child: Image.asset(category.assetPath, fit: BoxFit.cover)),
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
  );
}

class _FixtureCategory {
  const _FixtureCategory(this.name, this.assetPath);
  final String name;
  final String assetPath;
}

const _categories = <_FixtureCategory>[
  _FixtureCategory(
    'Living Room',
    'assets/furnitures/fixtures/categories/living-room.jpg',
  ),
  _FixtureCategory(
    'Bedroom',
    'assets/furnitures/fixtures/categories/bedroom.jpg',
  ),
  _FixtureCategory(
    'Dining Room',
    'assets/furnitures/fixtures/categories/dining.jpg',
  ),
  _FixtureCategory(
    'Home Office',
    'assets/furnitures/fixtures/categories/office.jpg',
  ),
];

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
                'assets/furnitures/fixtures/hero/chairs-heroimage.webp',
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
              onTap: () => context.push(AppRoutes.product(products[index].id)),
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
