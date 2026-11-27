import { Redirect } from 'expo-router';
import { useAuth } from '../lib/auth';

export default function Index() {
  const { session } = useAuth();
  if (!session) return <Redirect href="/login" />;
  return <Redirect href={session.type === 'member' ? '/home' : '/scan'} />;
}
