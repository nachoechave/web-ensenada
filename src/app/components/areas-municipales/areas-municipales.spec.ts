import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AreasMunicipales } from './areas-municipales';

describe('AreasMunicipales', () => {
  let component: AreasMunicipales;
  let fixture: ComponentFixture<AreasMunicipales>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AreasMunicipales],
    }).compileComponents();

    fixture = TestBed.createComponent(AreasMunicipales);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
