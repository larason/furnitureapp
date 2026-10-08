import 'error_presentation_mapper.dart';

sealed class AsyncViewState<T> {
  const AsyncViewState();
}

final class AsyncInitial<T> extends AsyncViewState<T> {
  const AsyncInitial();
}

final class AsyncLoading<T> extends AsyncViewState<T> {
  const AsyncLoading({this.previousData});

  final T? previousData;
}

final class AsyncContent<T> extends AsyncViewState<T> {
  const AsyncContent(this.data);

  final T data;
}

final class AsyncEmpty<T> extends AsyncViewState<T> {
  const AsyncEmpty();
}

final class AsyncFailure<T> extends AsyncViewState<T> {
  const AsyncFailure(this.error, {this.previousData});

  final ErrorPresentation error;
  final T? previousData;
}

final class AsyncRefreshing<T> extends AsyncViewState<T> {
  const AsyncRefreshing(this.data);

  final T data;
}

final class AsyncSubmitting<T> extends AsyncViewState<T> {
  const AsyncSubmitting({this.previousData});

  final T? previousData;
}
