import Ionicons from '@expo/vector-icons/Ionicons';
import { ComponentProps } from 'react';
import { ColorValue } from 'react-native';

export type IconName = ComponentProps<typeof Ionicons>['name'];

export function tabIcon(name: IconName) {
  return function TabBarIcon({ color, size }: { color: ColorValue; size: number }) {
    return <Ionicons name={name} color={color as string} size={size} />;
  };
}
