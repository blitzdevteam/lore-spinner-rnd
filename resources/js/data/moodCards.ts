import eyeImg from '@/assets/mood/Eye.svg';
import heartImg from '@/assets/mood/Heart.svg';
import mountainsImg from '@/assets/mood/Mountains.svg';
import spiralImg from '@/assets/mood/Spiral.svg';
import swordImg from '@/assets/mood/Sword.svg';
import { MOOD_IDS, type MoodId } from '@/data/moodStories';

export interface MoodCardConfig {
    id: MoodId;
    label: string;
    icon: string;
}

export const MOOD_CARD_CONFIGS: MoodCardConfig[] = [
    { id: 'heartfelt', label: 'Heartfelt', icon: heartImg },
    { id: 'adventurous', label: 'Adventurous', icon: mountainsImg },
    { id: 'mysterious', label: 'Mysterious', icon: eyeImg },
    { id: 'epic', label: 'Epic', icon: swordImg },
    { id: 'whimsical', label: 'Whimsical', icon: spiralImg },
];

export const MOOD_CARD_CONFIG_BY_ID = Object.fromEntries(
    MOOD_CARD_CONFIGS.map((config) => [config.id, config]),
) as Record<MoodId, MoodCardConfig>;

export { MOOD_TOP_PICK_SLUGS } from '@/data/moodStories';

export { MOOD_IDS };
