import 'category_detail.dart';
import 'category_summary.dart';

/// Approved development fixture categories.
///
/// These mirror `frontend/web/lib/homepage/fixtures.ts`
/// (`HOMEPAGE_CATEGORY_FIXTURES`) and the Laravel `CategorySeeder` level-1
/// rooms, so local fixture browsing, the website, and the backend agree on the
/// same taxonomy. Images reuse the bundled project fixtures.
///
/// The list is deliberately flat and top-level, exactly like CAT-003: the
/// seeder stores deeper descendants, but no fixture invents nested
/// subcategories the public contract does not expose. Descriptions are only
/// the ones already approved for fixtures; the rest stay null.
const List<CategoryDetail> fixtureCategoryDetails = <CategoryDetail>[
  CategoryDetail(
    id: 'fixture_living',
    name: 'Living Room',
    slug: 'living-room',
    description: 'Furniture for living spaces.',
    image: CategoryImage(
      url: '',
      assetPath: 'assets/furnitures/fixtures/categories/living-room.jpg',
    ),
  ),
  CategoryDetail(
    id: 'fixture_bedroom',
    name: 'Bedroom',
    slug: 'bedroom',
    image: CategoryImage(
      url: '',
      assetPath: 'assets/furnitures/fixtures/categories/bedroom.jpg',
    ),
  ),
  CategoryDetail(
    id: 'fixture_dining',
    name: 'Dining Room & Kitchen',
    slug: 'dining-room-kitchen',
    image: CategoryImage(
      url: '',
      assetPath: 'assets/furnitures/fixtures/categories/dining.jpg',
    ),
  ),
  CategoryDetail(
    id: 'fixture_office',
    name: 'Home Office & Corporate Workspaces',
    slug: 'home-office-corporate-workspaces',
    image: CategoryImage(
      url: '',
      assetPath: 'assets/furnitures/fixtures/categories/office.jpg',
    ),
  ),
];
