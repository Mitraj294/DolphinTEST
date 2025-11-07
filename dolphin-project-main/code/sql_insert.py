import sys
import json
from sqlalchemy import (
    select, create_engine, Table, Column, Integer, String, Float, MetaData, TIMESTAMP, func
)

# Currently using MySQL as local testing, change the line below to merge with dev team
engine = create_engine("mysql+pymysql://dolphin123:dolphin123@localhost/dolphin_clean")

metadata = MetaData()

# Define the table schema
results = Table(
    "results",
    metadata,
    Column("email", String(255), primary_key=True, nullable=False),
    Column("self_total_words", Integer),
    Column("conc_total_words", Integer),
    Column("adj_total_words", Integer),
    Column("self_a", Float),
    Column("self_b", Float),
    Column("self_c", Float),
    Column("self_d", Float),
    Column("self_avg", Float),
    Column("dec_approach", Float),
    Column("conc_a", Float),
    Column("conc_b", Float),
    Column("conc_c", Float),
    Column("conc_d", Float),
    Column("conc_avg", Float),
    Column("original_test_timestamp", TIMESTAMP, server_default=func.now()),
    Column("latest_test_timestamp", TIMESTAMP, server_default=func.now(), onupdate=func.now()),
    Column("tests_taken_count", Integer, server_default="1")
)

def repeat_test(data):
    try:
        with engine.begin() as conn:
            # First get the current tests_taken_count
            stmt = select(results.c.tests_taken_count).where(results.c.email == data["email"])
            current_count = conn.execute(stmt).scalar() or 1

            # Only update conc values + increment tests_taken_count
            fields_to_update = {
                "conc_a": data["conc_a"],
                "conc_b": data["conc_b"],
                "conc_c": data["conc_c"],
                "conc_d": data["conc_d"],
                "conc_total_words": data["conc_total_words"],
                "conc_avg": data["conc_avg"],
                "tests_taken_count": current_count + 1,
                # latest_test_timestamp will auto-update because of onupdate=func.now()
            }

            stmt = (
                results.update()
                .where(results.c.email == data["email"])
                .values(**fields_to_update)
            )
            conn.execute(stmt)
            print(f"Repeat test updated for {data['email']} (count={current_count + 1})")

    except Exception as e:
        print(f"SQLAlchemy Error in repeat_test: {e}")   
    

def insert_result(data):
    try:
        with engine.begin() as conn:  # handles commit/rollback automatically
            # Check if the email exists first
            stmt = select(results.c.email).where(results.c.email == data['email'])
            existing = conn.execute(stmt).fetchone()

            if existing:
                # Repeat test
                repeat_test(data)
                return

            # First test, insert normally
            conn.execute(results.insert().values(**data))
            print("Row inserted successfully (count=1)")

    except Exception as e:
        print(f"SQLAlchemy Error: {e}")


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("No data provided")
        sys.exit(1)

    # Load JSON from argv
    data = json.loads(sys.argv[1])
    insert_result(data)
