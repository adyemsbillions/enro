import { useCallback, useEffect, useRef, useState } from 'react';
import { useFocusEffect } from 'expo-router';
import { errMsg } from './api';

/** Loads data when the screen comes into focus; pull-to-refresh calls reload(). */
export function useLoad<T>(fn: () => Promise<T>) {
  const [data, setData] = useState<T | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const fnRef = useRef(fn);
  useEffect(() => {
    fnRef.current = fn;
  });

  const run = useCallback(async (pull = false) => {
    if (pull) setRefreshing(true);
    try {
      setData(await fnRef.current());
      setError(null);
    } catch (e) {
      setError(errMsg(e));
    } finally {
      setRefreshing(false);
    }
  }, []);

  useFocusEffect(useCallback(() => { run(); }, [run]));
  const refetch = useCallback(() => { run(false); }, [run]);

  // refetch: reload without the pull-to-refresh spinner (for background polling)
  return { data, setData, error, refreshing, reload: () => run(true), refetch, loading: data === null && error === null };
}
