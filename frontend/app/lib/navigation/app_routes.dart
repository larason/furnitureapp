abstract final class AppRoutes {
  static const home = '/';
  static const products = '/products';
  static const productPath = ':productId';
  static const categories = '/categories';
  static const categoryPath = ':categoryId';
  static const search = '/search';
  static const furnitureRequests = '/furniture-requests';
  static const contact = '/contact';
  static const account = '/account';
  static const signIn = '/sign-in';
  static const signUp = '/sign-up';
  static const authStatus = '/auth-status';

  static const homeName = 'home';
  static const productsName = 'products';
  static const productName = 'product';
  static const categoriesName = 'categories';
  static const categoryName = 'category';
  static const searchName = 'search';
  static const furnitureRequestsName = 'furniture-requests';
  static const contactName = 'contact';
  static const accountName = 'account';
  static const signInName = 'sign-in';
  static const signUpName = 'sign-up';
  static const authStatusName = 'auth-status';

  static String product(String productId) =>
      '$products/${Uri.encodeComponent(productId)}';

  static String category(String categoryId) =>
      '$categories/${Uri.encodeComponent(categoryId)}';

  static bool isValidResourceId(String? value) =>
      value != null && RegExp(r'^[A-Za-z0-9][A-Za-z0-9_-]*$').hasMatch(value);

  static bool isProtectedPath(String path) => path == account;

  static bool isAuthenticationPath(String path) =>
      path == signIn || path == signUp || path == authStatus;

  static String? safeIntendedDestination(String? destination) {
    if (destination == null || destination.isEmpty) return null;
    final uri = Uri.tryParse(destination);
    if (uri == null ||
        uri.hasScheme ||
        uri.hasAuthority ||
        uri.userInfo.isNotEmpty ||
        uri.hasQuery ||
        !uri.path.startsWith('/') ||
        isAuthenticationPath(uri.path) ||
        !isProtectedPath(uri.path)) {
      return null;
    }
    return uri.toString();
  }

  static String signInWithDestination(String destination) => Uri(
    path: signIn,
    queryParameters: <String, String>{'continue': destination},
  ).toString();

  static String authStatusWithDestination(String destination) => Uri(
    path: authStatus,
    queryParameters: <String, String>{'continue': destination},
  ).toString();
}
