import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AdminNoticiaForm } from './admin-noticia-form';

describe('AdminNoticiaForm', () => {
  let component: AdminNoticiaForm;
  let fixture: ComponentFixture<AdminNoticiaForm>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AdminNoticiaForm],
    }).compileComponents();

    fixture = TestBed.createComponent(AdminNoticiaForm);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
