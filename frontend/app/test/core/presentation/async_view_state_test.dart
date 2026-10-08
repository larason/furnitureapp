import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/presentation/async_view_state.dart';
import 'package:sl_furnitures/core/presentation/error_presentation_mapper.dart';

void main() {
  test('initial and loading are distinct states', () {
    const initial = AsyncInitial<List<String>>();
    const loading = AsyncLoading<List<String>>();

    expect(initial, isA<AsyncInitial<List<String>>>());
    expect(loading, isA<AsyncLoading<List<String>>>());
    expect(initial, isNot(isA<AsyncLoading<List<String>>>()));
  });

  test('refreshing and failure can preserve existing typed content', () {
    const data = <String>['chair'];
    const error = ErrorPresentation(
      title: 'Request failed',
      message: 'Try again later.',
    );
    const refreshing = AsyncRefreshing<List<String>>(data);
    const failure = AsyncFailure<List<String>>(error, previousData: data);

    expect(refreshing.data, data);
    expect(failure.previousData, data);
    expect(failure.error, error);
  });

  test('empty is distinct from content and failure', () {
    const empty = AsyncEmpty<List<String>>();
    const content = AsyncContent<List<String>>(<String>[]);
    const error = ErrorPresentation(title: 'Error', message: 'Failed.');
    const failure = AsyncFailure<List<String>>(error);

    expect(empty, isA<AsyncEmpty<List<String>>>());
    expect(content, isA<AsyncContent<List<String>>>());
    expect(failure, isA<AsyncFailure<List<String>>>());
    expect(empty, isNot(isA<AsyncContent<List<String>>>()));
  });
}
