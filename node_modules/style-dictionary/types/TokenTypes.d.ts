import { dimensionUnit } from '../lib/enums/index.js';
export type TokenTypeDimensionUnit = (typeof dimensionUnit)[keyof typeof dimensionUnit];
export interface TokenTypeDimension {
    value: number;
    unit: TokenTypeDimensionUnit;
}
